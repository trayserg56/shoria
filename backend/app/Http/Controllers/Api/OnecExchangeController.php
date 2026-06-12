<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Onec\CommerceMLImporter;
use App\Services\Onec\OrdersExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * CommerceML 2 endpoint для обмена с 1С.
 *
 * Схема сессии обмена (стандарт 1С «Обмен данными с сайтом»):
 *   GET  ?type=catalog&mode=checkauth   → успешная аутентификация (cookie сессии)
 *   GET  ?type=catalog&mode=init        → параметры обмена (file_limit, zip)
 *   POST ?type=catalog&mode=file        → загрузка файла import.xml
 *   GET  ?type=catalog&mode=import      → запуск импорта загруженного файла
 *   GET  ?type=sale&mode=query          → выгрузка заказов (XML)
 *   GET  ?type=sale&mode=success        → подтверждение завершения сессии
 */
class OnecExchangeController extends Controller
{
    public function __construct(
        private CommerceMLImporter $importer,
        private OrdersExporter $exporter,
    ) {}

    public function handle(Request $request): Response
    {
        $type = $request->query('type', 'catalog');
        $mode = $request->query('mode', 'checkauth');

        return match (true) {
            $mode === 'checkauth' => $this->checkAuth($request),
            $mode === 'init' => $this->init($request),
            $mode === 'file' => $this->uploadFile($request, $type),
            $mode === 'import' => $this->runImport($request, $type),
            $type === 'sale' && $mode === 'query' => $this->queryOrders($request),
            $mode === 'success' => $this->success(),
            default => response('failure' . PHP_EOL . 'Неизвестный режим', 400),
        };
    }

    private function checkAuth(Request $request): Response
    {
        // 1С ожидает ответ "success\n<name>\n<value>" (имя и значение сессионной cookie)
        $sessionName = session()->getName();
        $request->session()->regenerate();

        return response("success\n{$sessionName}\n" . $request->session()->getId());
    }

    private function init(Request $request): Response
    {
        // Возвращаем лимит файла и поддержку zip
        $limit = min(
            (int) ini_get('post_max_size') * 1024 * 1024,
            (int) ini_get('upload_max_filesize') * 1024 * 1024,
            64 * 1024 * 1024 // 64MB максимум
        );

        return response("zip=no\nfile_limit={$limit}");
    }

    private function uploadFile(Request $request, string $type): Response
    {
        $filename = $request->query('filename', 'import.xml');
        $content = $request->getContent();

        if (empty($content)) {
            return response('failure' . PHP_EOL . 'Пустой файл', 400);
        }

        // Изображения (из папки import_files/) сохраняем на диск, XML — в Redis
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            // Filename может содержать путь: "import_files/product-001.jpg"
            $storagePath = '1c-import/' . ltrim($filename, '/');
            Storage::disk('public')->put($storagePath, $content);

            return response('success');
        }

        $key = "1c_exchange_{$type}_{$filename}";
        Cache::put($key, $content, now()->addHour());

        return response('success');
    }

    private function runImport(Request $request, string $type): Response
    {
        $filename = $request->query('filename', 'import.xml');
        $key = "1c_exchange_{$type}_{$filename}";
        $content = Cache::get($key);

        if (! $content) {
            return response('failure' . PHP_EOL . 'Файл не найден, загрузите его сначала', 404);
        }

        try {
            $base = strtolower(basename($filename, '.xml'));

            if (in_array($base, ['offers', 'prices', 'rests'], true) || $type === 'offers') {
                // offers.xml — цены + остатки вместе
                // prices.xml — только цены (offers.xml без блока складов)
                // rests.xml  — только остатки (offers.xml без блока цен)
                // Один метод обрабатывает все три: отсутствующий блок просто пропускается
                $counts = $this->importer->importOffers($content);
            } else {
                $counts = $this->importer->importCatalog($content);
            }

            Cache::forget($key);

            return response(
                "success\nОбработано: {$counts['created']} создано, {$counts['updated']} обновлено, {$counts['skipped']} пропущено"
            );
        } catch (\Throwable $e) {
            return response('failure' . PHP_EOL . $e->getMessage(), 500);
        }
    }

    private function queryOrders(Request $request): Response
    {
        try {
            $since = $request->query('since');
            $xml = $this->exporter->export($since);

            return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        } catch (\Throwable $e) {
            return response('failure' . PHP_EOL . $e->getMessage(), 500);
        }
    }

    private function success(): Response
    {
        return response('success');
    }
}
