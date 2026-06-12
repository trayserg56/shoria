<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Создаём дефолтный склад и тип цены «Розница»
        $warehouseId = DB::table('warehouses')->insertGetId([
            'name' => 'Основной склад',
            'code' => 'main',
            'city' => null,
            'priority' => 1,
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('price_types')->insert([
            'code' => 'retail',
            'name' => 'Розница',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Переносим остатки товаров без вариантов в stock_levels
        $products = DB::table('products')->select('id', 'stock')->get();
        foreach ($products as $product) {
            DB::table('stock_levels')->insert([
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'qty' => max(0, (int) $product->stock),
                'reserved_qty' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Переносим остатки вариантов
        $variants = DB::table('product_variants')->select('id', 'stock')->get();
        foreach ($variants as $variant) {
            DB::table('stock_levels')->insert([
                'warehouse_id' => $warehouseId,
                'product_id' => null,
                'product_variant_id' => $variant->id,
                'qty' => max(0, (int) $variant->stock),
                'reserved_qty' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stock_levels')->delete();
        DB::table('price_types')->where('code', 'retail')->delete();
        DB::table('warehouses')->where('code', 'main')->delete();
    }
};
