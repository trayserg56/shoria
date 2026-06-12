<?php

namespace App\Console\Commands\Onec;

use App\Services\Onec\CommerceMLImporter;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    protected $signature = 'onec:sync-products {file : Путь к файлу import.xml}';
    protected $description = 'Импорт товаров и категорий из файла CommerceML 2 (import.xml)';

    public function handle(CommerceMLImporter $importer): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("Файл не найден: {$file}");
            return self::FAILURE;
        }

        $this->info("Импорт товаров из {$file}...");

        try {
            $counts = $importer->importCatalog(file_get_contents($file));
            $this->table(['Создано', 'Обновлено', 'Пропущено'], [
                [$counts['created'], $counts['updated'], $counts['skipped']],
            ]);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
