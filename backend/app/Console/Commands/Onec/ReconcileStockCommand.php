<?php

namespace App\Console\Commands\Onec;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStockCommand extends Command
{
    protected $signature = 'onec:reconcile {--dry-run : Только показать расхождения, не исправлять}';

    protected $description = 'Сверить денормализованный stock в products/variants с реальными остатками из stock_levels';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fixed = 0;
        $ok = 0;

        // Товары без вариантов
        $productLevels = StockLevel::query()
            ->whereNull('product_variant_id')
            ->selectRaw('product_id, SUM(GREATEST(qty - reserved_qty, 0)) as available')
            ->groupBy('product_id')
            ->pluck('available', 'product_id');

        Product::query()->chunk(500, function ($products) use ($productLevels, $dryRun, &$fixed, &$ok): void {
            foreach ($products as $product) {
                $expected = (int) ($productLevels[$product->id] ?? 0);
                $actual = (int) $product->stock;

                if ($expected !== $actual) {
                    $this->line("Товар #{$product->id} «{$product->name}»: stock={$actual} → {$expected}");
                    if (! $dryRun) {
                        DB::table('products')->where('id', $product->id)->update(['stock' => $expected]);
                    }
                    $fixed++;
                } else {
                    $ok++;
                }
            }
        });

        // Варианты
        $variantLevels = StockLevel::query()
            ->whereNotNull('product_variant_id')
            ->selectRaw('product_variant_id, SUM(GREATEST(qty - reserved_qty, 0)) as available')
            ->groupBy('product_variant_id')
            ->pluck('available', 'product_variant_id');

        ProductVariant::query()->chunk(500, function ($variants) use ($variantLevels, $dryRun, &$fixed, &$ok): void {
            foreach ($variants as $variant) {
                $expected = (int) ($variantLevels[$variant->id] ?? 0);
                $actual = (int) $variant->stock;

                if ($expected !== $actual) {
                    $this->line("Вариант #{$variant->id} (товар #{$variant->product_id}): stock={$actual} → {$expected}");
                    if (! $dryRun) {
                        DB::table('product_variants')->where('id', $variant->id)->update(['stock' => $expected]);
                    }
                    $fixed++;
                } else {
                    $ok++;
                }
            }
        });

        $mode = $dryRun ? '[dry-run]' : '';
        $this->info("{$mode} Готово. Исправлено: {$fixed}, совпадает: {$ok}");

        return self::SUCCESS;
    }
}
