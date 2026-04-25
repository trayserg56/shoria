<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('characteristics')->nullable()->after('description');
        });

        $products = DB::table('products')
            ->select(['id', 'brand', 'sku'])
            ->get();

        foreach ($products as $product) {
            $characteristics = [
                [
                    'group' => 'Общие характеристики',
                    'name' => 'Артикул',
                    'value' => (string) ($product->sku ?: '—'),
                ],
                [
                    'group' => 'Общие характеристики',
                    'name' => 'Бренд',
                    'value' => (string) ($product->brand ?: 'Shoria'),
                ],
                [
                    'group' => 'Общие характеристики',
                    'name' => 'Материал верха',
                    'value' => 'Текстиль / синтетика',
                ],
                [
                    'group' => 'Дополнительно',
                    'name' => 'Гарантия',
                    'value' => '1 год',
                ],
            ];

            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'characteristics' => json_encode($characteristics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('characteristics');
        });
    }
};
