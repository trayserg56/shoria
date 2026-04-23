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
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable()->after('product_id');
            $table->string('color_label', 64)->nullable()->after('size_label');
            $table->unique(['product_id', 'slug']);
        });

        $variants = DB::table('product_variants')
            ->select('id', 'product_id', 'size_label', 'color_label')
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        $slugRegistry = [];

        foreach ($variants as $variant) {
            $parts = array_filter([
                $variant->color_label,
                $variant->size_label,
            ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
            $base = Str::slug(implode('-', $parts));

            if ($base === '') {
                $base = 'variant';
            }

            $slug = $base;
            $suffix = 2;
            $productKey = (string) $variant->product_id;
            $slugRegistry[$productKey] = $slugRegistry[$productKey] ?? [];

            while (in_array($slug, $slugRegistry[$productKey], true)) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $slugRegistry[$productKey][] = $slug;

            DB::table('product_variants')
                ->where('id', $variant->id)
                ->update(['slug' => $slug]);
        }

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable(false)->change();
        });

        Schema::create('product_variant_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('alt')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_variant_id', 'is_cover', 'sort_order'], 'variant_images_cover_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_images');

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_product_id_slug_unique');
            $table->dropColumn(['slug', 'color_label']);
        });
    }
};

