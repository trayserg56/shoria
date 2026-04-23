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
            $table->string('brand', 120)->nullable()->after('name')->index();
        });

        $slugBrandMap = [
            'aero-pulse-300' => 'Nike',
            'city-frame-one' => 'Nike',
            'neon-track-x' => 'Puma',
            'vault-signature' => 'Balenciaga',
            'cloud-step-v2' => 'ASICS',
            'sprint-form-pro' => 'Nike',
            'metro-glide' => 'New Balance',
            'daily-ease' => 'Adidas',
            'road-tempo-elite' => 'Nike',
            'trail-ridge-gtx' => 'Salomon',
            'block-tone-high' => 'Converse',
            'archive-reserve' => 'Converse',
        ];

        DB::table('products')
            ->select(['id', 'slug', 'brand'])
            ->orderBy('id')
            ->chunkById(250, function ($products) use ($slugBrandMap): void {
                foreach ($products as $product) {
                    $currentBrand = trim((string) ($product->brand ?? ''));

                    if ($currentBrand !== '') {
                        continue;
                    }

                    $brand = $slugBrandMap[$product->slug] ?? $this->resolveFallbackBrand($product->slug);

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'brand' => $brand,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['brand']);
            $table->dropColumn('brand');
        });
    }

    private function resolveFallbackBrand(string $slug): string
    {
        $brands = [
            'Shoria',
            'Nike',
            'Adidas',
            'Puma',
            'ASICS',
            'New Balance',
            'Reebok',
            'Converse',
            'Under Armour',
            'Salomon',
        ];

        return $brands[abs(crc32($slug)) % count($brands)];
    }
};
