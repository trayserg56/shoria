<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedTypes = ['news', 'guide', 'collection', 'promo'];
        $requestedType = $request->string('type')->toString();
        $type = in_array($requestedType, $allowedTypes, true) ? $requestedType : null;

        $news = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->when($type, fn ($query) => $query->where('content_type', $type))
            ->orderByDesc('published_at')
            ->paginate(6);

        return response()->json($news);
    }

    public function show(string $slug): JsonResponse
    {
        $post = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('slug', $slug)
            ->firstOrFail();

        $related = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'content_type', 'excerpt', 'cover_url', 'published_at']);
        $manualProducts = $post->products()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('products.is_active', true)
            ->get();
        $spotlightProducts = $manualProducts->isNotEmpty()
            ? $manualProducts->map(fn (Product $product) => $this->mapProductCard($product))->values()
            : $this->resolveSpotlightProducts($post->content_type);

        return response()->json([
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content_type' => $post->content_type,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'cover_url' => $post->cover_url,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'published_at' => optional($post->published_at)?->toIso8601String(),
            'related' => $related,
            'spotlight_products' => $spotlightProducts,
        ]);
    }

    private function resolveSpotlightProducts(string $contentType): Collection
    {
        $query = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true)
            ->where('stock', '>', 0);

        match ($contentType) {
            'guide' => $query
                ->whereHas('category', function (Builder $categoryQuery): void {
                    $categoryQuery->whereIn('slug', ['running', 'road-running', 'trail-running']);
                })
                ->orderByDesc('is_featured')
                ->orderByDesc('is_customer_choice')
                ->orderByDesc('is_hit')
                ->orderByDesc('is_new')
                ->orderBy('sort_order'),
            'collection' => $query
                ->whereHas('category', function (Builder $categoryQuery): void {
                    $categoryQuery->whereIn('slug', [
                        'lifestyle',
                        'classic-daily',
                        'urban-comfort',
                        'street',
                        'bold-street',
                        'premium',
                        'limited-edition',
                    ]);
                })
                ->orderByDesc('is_new')
                ->orderByDesc('is_featured')
                ->orderByDesc('is_customer_choice')
                ->orderBy('sort_order'),
            'promo' => $query
                ->whereNotNull('old_price')
                ->orderByRaw('(old_price - price) DESC')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order'),
            default => $query
                ->orderByDesc('is_new')
                ->orderByDesc('is_featured')
                ->orderByDesc('is_hit')
                ->orderBy('sort_order'),
        };

        return $query
            ->limit(3)
            ->get()
            ->map(fn (Product $product) => $this->mapProductCard($product))
            ->values();
    }

    private function mapProductCard(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
            'currency' => $product->currency,
            'image_url' => $product->images
                ->sortBy([
                    ['is_cover', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->first()?->url,
            'category' => $product->category ? [
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
        ];
    }
}
