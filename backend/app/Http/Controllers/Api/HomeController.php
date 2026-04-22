<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\NewsPost;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = Carbon::now();

        $banners = Banner::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->limit(8)
            ->get();
        $banner = $banners->first();

        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'image_url']);

        $products = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,is_cover,sort_order'])
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
                'currency' => $product->currency,
                'category' => $product->category ? [
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ] : null,
                'image_url' => $product->images
                    ->sortBy([
                        ['is_cover', 'desc'],
                        ['sort_order', 'asc'],
                    ])
                    ->first()?->url,
            ]);

        $news = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover_url', 'published_at']);

        return response()->json([
            'banner' => $banner,
            'banners' => $banners,
            'categories' => $categories,
            'featured_products' => $products,
            'news' => $news,
        ]);
    }
}
