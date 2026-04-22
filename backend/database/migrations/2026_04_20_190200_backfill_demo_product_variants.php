<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $targetSlugs = ['cloud-step-v2', 'sprint-form-pro'];
        $now = now();

        $products = DB::table('products')
            ->select('id', 'slug', 'sku')
            ->whereIn('slug', $targetSlugs)
            ->get();

        foreach ($products as $product) {
            $existing = DB::table('product_variants')
                ->where('product_id', $product->id)
                ->exists();

            if ($existing) {
                continue;
            }

            $sizes = [
                ['label' => 'EU 40', 'stock' => 2, 'sort_order' => 1],
                ['label' => 'EU 41', 'stock' => 4, 'sort_order' => 2],
                ['label' => 'EU 42', 'stock' => 5, 'sort_order' => 3],
                ['label' => 'EU 43', 'stock' => 3, 'sort_order' => 4],
            ];

            foreach ($sizes as $size) {
                DB::table('product_variants')->insert([
                    'product_id' => $product->id,
                    'size_label' => $size['label'],
                    'sku' => ($product->sku ?? 'SH-VARIANT') . '-' . str_replace(' ', '', $size['label']),
                    'price' => null,
                    'stock' => $size['stock'],
                    'is_active' => true,
                    'sort_order' => $size['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $productIds = DB::table('products')
            ->whereIn('slug', ['cloud-step-v2', 'sprint-form-pro'])
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return;
        }

        DB::table('product_variants')
            ->whereIn('product_id', $productIds)
            ->whereIn('size_label', ['EU 40', 'EU 41', 'EU 42', 'EU 43'])
            ->delete();
    }
};
