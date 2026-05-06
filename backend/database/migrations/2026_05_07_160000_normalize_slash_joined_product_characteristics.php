<?php

use App\Models\Product;
use App\Support\ProductCharacteristics;
use Illuminate\Database\Migrations\Migration;

/**
 * Ранние данные хранили «Материал верха» одной строкой «Текстиль / синтетика».
 * Для отдельных ссылок и whereJsonContains в каталоге нужны отдельные кортежи в JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Product::query()
            ->whereNotNull('characteristics')
            ->select(['id', 'characteristics'])
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $raw = $product->characteristics;
                    if (! is_array($raw) || $raw === []) {
                        continue;
                    }

                    $before = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $flat = ProductCharacteristics::flattenTuples($raw);
                    $next = [];
                    foreach ($flat as $t) {
                        $row = [
                            'name' => $t['name'],
                            'value' => $t['value'],
                        ];
                        if ($t['group'] !== null && $t['group'] !== '') {
                            $row['group'] = $t['group'];
                        }
                        $next[] = $row;
                    }

                    $after = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($before === $after) {
                        continue;
                    }

                    $product->characteristics = $next;
                    $product->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Объединять значения обратно в одну строку нельзя без потери структуры.
    }
};
