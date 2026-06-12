<?php

namespace App\Console\Commands\Onec;

use App\Services\Onec\CommerceMLImporter;
use Illuminate\Console\Command;

class SyncStockCommand extends Command
{
    protected $signature = 'onec:sync-stock {file : Путь к файлу offers.xml}';
    protected $description = 'Импорт цен и остатков из файла CommerceML 2 (offers.xml)';

    public function handle(CommerceMLImporter $importer): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("Файл не найден: {$file}");
            return self::FAILURE;
        }

        $this->info("Импорт цен и остатков из {$file}...");

        try {
            $counts = $importer->importOffers(file_get_contents($file));
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
