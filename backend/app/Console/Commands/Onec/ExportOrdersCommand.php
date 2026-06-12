<?php

namespace App\Console\Commands\Onec;

use App\Services\Onec\OrdersExporter;
use Illuminate\Console\Command;

class ExportOrdersCommand extends Command
{
    protected $signature = 'onec:export-orders {--since= : Дата с которой выгружать (Y-m-d)} {--output= : Путь к выходному файлу}';
    protected $description = 'Выгрузка заказов в формат CommerceML 2 для 1С';

    public function handle(OrdersExporter $exporter): int
    {
        $this->info('Выгрузка заказов...');

        try {
            $xml = $exporter->export($this->option('since'));

            $output = $this->option('output');
            if ($output) {
                file_put_contents($output, $xml);
                $this->info("Сохранено в: {$output}");
            } else {
                $this->line($xml);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
