<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\NewsPost;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
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
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,is_cover,sort_order',
            ])
            ->withCount([
                'reviews as reviews_count' => fn (Builder $reviewsQuery) => $reviewsQuery->where('is_active', true),
            ])
            ->withAvg([
                'reviews as reviews_avg_rating' => fn (Builder $reviewsQuery) => $reviewsQuery->where('is_active', true),
            ], 'rating')
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(function (Product $product): array {
                $primaryCategory = $product->category ?? $product->categories->sortBy('name')->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brandEntity?->name
                        ?? ($product->brand !== null && trim($product->brand) !== '' ? $product->brand : null),
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
                    'currency' => $product->currency,
                    'reviews_summary' => [
                        'count' => (int) ($product->reviews_count ?? 0),
                        'average' => $product->reviews_avg_rating !== null
                            ? round((float) $product->reviews_avg_rating, 1)
                            : null,
                    ],
                    'category' => $primaryCategory ? [
                        'name' => $primaryCategory->name,
                        'slug' => $primaryCategory->slug,
                    ] : null,
                    'image_url' => $product->images
                        ->sortBy([
                            ['is_cover', 'desc'],
                            ['sort_order', 'asc'],
                        ])
                        ->first()?->url,
                ];
            });

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
