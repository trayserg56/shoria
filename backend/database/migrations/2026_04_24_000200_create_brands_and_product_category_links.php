<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('brand')
                ->constrained('brands')
                ->nullOnDelete();
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'product_id']);
        });

        $now = now();
        $brandIdsByName = [];

        $brandNames = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand')
            ->map(fn (string $brand): string => trim($brand))
            ->filter()
            ->values();

        foreach ($brandNames as $brandName) {
            $baseSlug = Str::slug($brandName) ?: 'brand';
            $slug = $baseSlug;
            $suffix = 2;

            while (DB::table('brands')->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $brandId = DB::table('brands')->insertGetId([
                'name' => $brandName,
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $brandIdsByName[$brandName] = $brandId;
        }

        DB::table('products')
            ->select('id', 'brand')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->orderBy('id')
            ->chunk(250, function ($products) use ($brandIdsByName): void {
                foreach ($products as $product) {
                    $brand = trim((string) $product->brand);
                    $brandId = $brandIdsByName[$brand] ?? null;

                    if ($brandId === null) {
                        continue;
                    }

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['brand_id' => $brandId]);
                }
            });

        DB::table('products')
            ->select('id', 'category_id')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunk(250, function ($products) use ($now): void {
                foreach ($products as $product) {
                    DB::table('category_product')->updateOrInsert(
                        [
                            'category_id' => $product->category_id,
                            'product_id' => $product->id,
                        ],
                        [
                            'updated_at' => $now,
                            'created_at' => $now,
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
