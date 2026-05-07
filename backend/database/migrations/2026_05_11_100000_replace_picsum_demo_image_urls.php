<?php

use App\Support\Seed\RemotePlaceholderImages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * picsum.photos часто отдаёт 403 на витрине (Cloudflare). Заменяем на Unsplash из того же пула, что load-сидер.
 */
return new class extends Migration
{
    public function up(): void
    {
        $productIds = DB::table('product_images')
            ->where('url', 'like', '%picsum.photos%')
            ->orderBy('id')
            ->pluck('id');

        foreach ($productIds as $index => $id) {
            DB::table('product_images')
                ->where('id', $id)
                ->update(['url' => RemotePlaceholderImages::productImageUrl((int) $index)]);
        }

        $categoryIds = DB::table('categories')
            ->where('image_url', 'like', '%picsum.photos%')
            ->orderBy('id')
            ->pluck('id');

        foreach ($categoryIds as $index => $id) {
            DB::table('categories')
                ->where('id', $id)
                ->update(['image_url' => RemotePlaceholderImages::categoryImageUrl((int) $index + 1)]);
        }
    }

    public function down(): void
    {
        // намеренно без отката: не восстанавливаем нестабильные URL
    }
};
