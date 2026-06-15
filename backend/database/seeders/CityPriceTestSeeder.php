<?php

namespace Database\Seeders;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Тестовые данные: разные цены и остатки по городам.
 * Позволяет проверить city-based pricing на витрине.
 *
 * Москва: базовая цена из products.price
 * СПб: скидка 10% от базовой цены
 */
class CityPriceTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Создать или найти типы цен для городов
            $moscowType = PriceType::firstOrCreate(
                ['code' => 'retail_moscow'],
                ['name' => 'Розница Москва', 'is_default' => false],
            );

            $spbType = PriceType::firstOrCreate(
                ['code' => 'retail_spb'],
                ['name' => 'Розница СПб', 'is_default' => false],
            );

            // 2. Привязать типы цен к складам и проставить город
            Warehouse::where('code', 'osnovnoi_sklad_moskva')
                ->update([
                    'city' => 'Москва',
                    'price_type_id' => $moscowType->id,
                ]);

            Warehouse::where('code', 'sklad_spb')
                ->update([
                    'city' => 'Санкт-Петербург',
                    'price_type_id' => $spbType->id,
                ]);

            // Также убедимся что основной склад имеет город (для fallback)
            Warehouse::where('code', 'main')
                ->whereNull('city')
                ->update(['city' => 'Москва']);

            $moscowWarehouse = Warehouse::where('code', 'osnovnoi_sklad_moskva')->first();
            $spbWarehouse = Warehouse::where('code', 'sklad_spb')->first();
            $mainWarehouse = Warehouse::where('code', 'main')->first();

            if (! $moscowWarehouse || ! $spbWarehouse) {
                $this->command->error('Склады Москвы или СПб не найдены. Убедитесь, что они существуют.');

                return;
            }

            // 3. Для всех товаров создать цены по типам и остатки по складам
            $products = Product::all();
            $inserted = 0;

            foreach ($products as $product) {
                $basePrice = (float) $product->price;
                if ($basePrice <= 0) {
                    continue;
                }

                $moscowPrice = round($basePrice, 2);
                $spbPrice = round($basePrice * 0.90, 2); // СПб дешевле на 10%

                // Цена Москва (на уровне товара)
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'price_type_id' => $moscowType->id,
                    ],
                    ['price' => $moscowPrice, 'currency' => 'RUB'],
                );

                // Цена СПб (на уровне товара)
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'price_type_id' => $spbType->id,
                    ],
                    ['price' => $spbPrice, 'currency' => 'RUB'],
                );

                // Остатки: Москва
                StockLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'warehouse_id' => $moscowWarehouse->id,
                    ],
                    ['qty' => rand(5, 50), 'reserved_qty' => 0],
                );

                // Остатки: СПб (меньше, чем в Москве)
                StockLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'warehouse_id' => $spbWarehouse->id,
                    ],
                    ['qty' => rand(1, 15), 'reserved_qty' => 0],
                );

                $inserted++;
            }

            // 4. Для вариантов — тоже заполнить остатки по складам
            $variants = ProductVariant::all();
            foreach ($variants as $variant) {
                $product = $products->find($variant->product_id);
                if (! $product) {
                    continue;
                }

                $basePrice = (float) ($variant->price ?: $product->price);

                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'price_type_id' => $moscowType->id,
                    ],
                    ['price' => round($basePrice, 2), 'currency' => 'RUB'],
                );

                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'price_type_id' => $spbType->id,
                    ],
                    ['price' => round($basePrice * 0.90, 2), 'currency' => 'RUB'],
                );

                StockLevel::updateOrCreate(
                    [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'warehouse_id' => $moscowWarehouse->id,
                    ],
                    ['qty' => rand(3, 30), 'reserved_qty' => 0],
                );

                StockLevel::updateOrCreate(
                    [
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'warehouse_id' => $spbWarehouse->id,
                    ],
                    ['qty' => rand(1, 10), 'reserved_qty' => 0],
                );
            }

            $this->command->info("Готово: {$inserted} товаров. Москва = базовая цена, СПб = −10%.");
            $this->command->info("Типы цен: Розница Москва (id={$moscowType->id}), Розница СПб (id={$spbType->id})");
        });
    }
}
