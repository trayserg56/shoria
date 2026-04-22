<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TrackingEvent;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searchTerm = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString();
        $priceMin = $request->query('price_min');
        $priceMax = $request->query('price_max');
        $inStock = filter_var($request->query('in_stock', false), FILTER_VALIDATE_BOOLEAN);
        $tags = $this->parseTagFilters($request);

        $query = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true);

        if ($request->filled('category')) {
            $categorySlug = $request->string('category')->toString();
            $categoryIds = Category::query()
                ->where('slug', $categorySlug)
                ->orWhereHas('parent', fn (Builder $parentQuery) => $parentQuery->where('slug', $categorySlug))
                ->pluck('id');

            $query->whereIn('category_id', $categoryIds);
        }

        if ($searchTerm !== '') {
            $searchVariants = $this->buildSearchVariants($searchTerm);

            $query->where(function ($searchQuery) use ($searchVariants): void {
                foreach ($searchVariants as $index => $variant) {
                    $needle = '%' . $variant . '%';

                    $method = $index === 0 ? 'where' : 'orWhere';

                    $searchQuery->{$method}(function ($variantQuery) use ($needle): void {
                        $variantQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$needle])
                            ->orWhereHas('category', function ($categoryQuery) use ($needle): void {
                                $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                            });
                    });
                }
            });
        }

        if (is_numeric($priceMin)) {
            $query->where('price', '>=', (float) $priceMin);
        }

        if (is_numeric($priceMax)) {
            $query->where('price', '<=', (float) $priceMax);
        }

        if ($inStock) {
            $query->where('stock', '>', 0);
        }

        if ($tags !== []) {
            $query->where(function (Builder $tagQuery) use ($tags): void {
                foreach ($tags as $index => $tag) {
                    $column = $this->tagToColumn($tag);

                    if ($column === null) {
                        continue;
                    }

                    $method = $index === 0 ? 'where' : 'orWhere';
                    $tagQuery->{$method}($column, true);
                }
            });
        }

        $this->applySort($query, $sort);

        $products = $query
            ->paginate(12)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
                'currency' => $product->currency,
                'stock' => $product->stock,
                'tags' => $this->resolveProductTags($product),
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

        return response()->json($products);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('sort_order'),
            'price_desc' => $query->orderByDesc('price')->orderBy('sort_order'),
            'name_asc' => $query->orderBy('name')->orderBy('sort_order'),
            'name_desc' => $query->orderByDesc('name')->orderBy('sort_order'),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order'),
        };
    }

    public function suggest(Request $request): JsonResponse
    {
        $queryText = trim($request->string('q')->toString());

        if (mb_strlen($queryText) < 2) {
            return response()->json([
                'query' => $queryText,
                'suggestions' => [],
            ]);
        }

        $searchVariants = $this->buildSearchVariants($queryText);

        $prefixVariants = array_map(
            static fn (string $variant): string => $variant . '%',
            $searchVariants,
        );

        $products = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true)
            ->where(function ($searchQuery) use ($searchVariants): void {
                foreach ($searchVariants as $index => $variant) {
                    $needle = '%' . $variant . '%';

                    $method = $index === 0 ? 'where' : 'orWhere';

                    $searchQuery->{$method}(function ($variantQuery) use ($needle): void {
                        $variantQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$needle])
                            ->orWhereHas('category', function ($categoryQuery) use ($needle): void {
                                $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                            });
                    });
                }
            })
            ->orderByRaw(
                'CASE WHEN ' . implode(' OR ', array_fill(0, count($prefixVariants), 'LOWER(name) LIKE ?')) . ' THEN 0 ELSE 1 END',
                $prefixVariants,
            )
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return response()->json([
            'query' => $queryText,
            'suggestions' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'currency' => $product->currency,
                'tags' => $this->resolveProductTags($product),
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
            ])->values(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function buildSearchVariants(string $queryText): array
    {
        $normalized = mb_strtolower(trim($queryText));
        $transliterated = $this->transliterateCyrillicToLatin($normalized);

        return array_values(array_unique(array_filter([
            $normalized,
            $transliterated,
        ])));
    }

    private function transliterateCyrillicToLatin(string $value): string
    {
        $map = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'i',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
        ];

        return strtr($value, $map);
    }

    /**
     * @return array<int, string>
     */
    private function parseTagFilters(Request $request): array
    {
        $rawTags = $request->query('tags');

        if (is_string($rawTags)) {
            $items = explode(',', $rawTags);
        } elseif (is_array($rawTags)) {
            $items = $rawTags;
        } else {
            $items = [];
        }

        $normalized = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            $items,
        );

        return array_values(array_filter(array_unique($normalized), static fn (string $tag): bool => in_array($tag, [
            'hit',
            'new',
            'customer_choice',
        ], true)));
    }

    private function tagToColumn(string $tag): ?string
    {
        return match ($tag) {
            'hit' => 'is_hit',
            'new' => 'is_new',
            'customer_choice' => 'is_customer_choice',
            default => null,
        };
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function resolveProductTags(Product $product): array
    {
        $tags = [];

        if ($product->is_hit) {
            $tags[] = ['code' => 'hit', 'label' => 'Хит'];
        }

        if ($product->is_new) {
            $tags[] = ['code' => 'new', 'label' => 'Новинка'];
        }

        if ($product->is_customer_choice) {
            $tags[] = ['code' => 'customer_choice', 'label' => 'Выбор покупателей'];
        }

        return $tags;
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->with([
                'category:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
                'variants:id,product_id,size_label,sku,price,stock,is_active,sort_order',
            ])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $variants = $product->variants
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'size_label' => $variant->size_label,
                'sku' => $variant->sku,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'stock' => $variant->stock,
            ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'price' => (float) $product->price,
            'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
            'currency' => $product->currency,
            'stock' => $product->stock,
            'tags' => $this->resolveProductTags($product),
            'has_variants' => $variants->isNotEmpty(),
            'category' => $product->category ? [
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'variants' => $variants,
            'images' => $product->images
                ->sortBy([
                    ['is_cover', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->values()
                ->map(fn ($image) => [
                    'url' => $image->url,
                    'alt' => $image->alt,
                    'is_cover' => $image->is_cover,
                ]),
        ]);
    }

    public function recommendations(string $slug): JsonResponse
    {
        $currentProduct = Product::query()
            ->select(['id', 'slug'])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $coPurchaseProductIds = OrderItem::query()
            ->where('product_id', $currentProduct->id)
            ->whereHas('order', function (Builder $query): void {
                $query
                    ->whereIn('status', ['paid', 'processing', 'completed'])
                    ->where('placed_at', '>=', now()->subDays(180));
            })
            ->pluck('order_id')
            ->unique()
            ->whenNotEmpty(function ($orderIds) use ($currentProduct) {
                return OrderItem::query()
                    ->selectRaw('product_id, COUNT(*) as score')
                    ->whereIn('order_id', $orderIds)
                    ->where('product_id', '!=', $currentProduct->id)
                    ->whereNotNull('product_id')
                    ->groupBy('product_id')
                    ->orderByDesc('score')
                    ->limit(8)
                    ->pluck('product_id');
            }, function () {
                return collect();
            })
            ->values();

        if ($coPurchaseProductIds->isNotEmpty()) {
            $coPurchaseProducts = Product::query()
                ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
                ->where('is_active', true)
                ->whereIn('id', $coPurchaseProductIds)
                ->get()
                ->sortBy(function (Product $product) use ($coPurchaseProductIds): int {
                    return (int) $coPurchaseProductIds->search($product->id);
                })
                ->take(8)
                ->values();

            if ($coPurchaseProducts->isNotEmpty()) {
                return response()->json([
                    'source' => 'co_purchase',
                    'data' => $coPurchaseProducts->map(fn (Product $product) => $this->productCardPayload($product))->values(),
                ]);
            }
        }

        $events = TrackingEvent::query()
            ->where('event_name', 'view_product')
            ->whereNotNull('session_id')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->orderByDesc('occurred_at')
            ->limit(3000)
            ->get(['session_id', 'payload']);

        $targetSessionIds = $events
            ->filter(function (TrackingEvent $event) use ($slug): bool {
                return (($event->payload['slug'] ?? null) === $slug);
            })
            ->pluck('session_id')
            ->filter()
            ->unique()
            ->values();

        $recommendedSlugCounts = $events
            ->filter(function (TrackingEvent $event) use ($targetSessionIds): bool {
                return $targetSessionIds->contains($event->session_id);
            })
            ->map(fn (TrackingEvent $event) => $event->payload['slug'] ?? null)
            ->filter(fn (?string $eventSlug): bool => is_string($eventSlug))
            ->reject(fn (string $eventSlug): bool => $eventSlug === $slug)
            ->countBy()
            ->sortDesc()
            ->take(8);

        $recommendedSlugs = $recommendedSlugCounts->keys()->values();

        if ($recommendedSlugs->isEmpty()) {
            $fallbackProducts = Product::query()
                ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
                ->where('is_active', true)
                ->where('id', '!=', $currentProduct->id)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit(8)
                ->get();

            return response()->json([
                'source' => 'featured_fallback',
                'data' => $fallbackProducts->map(fn (Product $product) => $this->productCardPayload($product))->values(),
            ]);
        }

        $recommendedProducts = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true)
            ->whereIn('slug', $recommendedSlugs)
            ->get()
            ->sortBy(function (Product $product) use ($recommendedSlugCounts): int {
                return -1 * (int) ($recommendedSlugCounts[$product->slug] ?? 0);
            })
            ->take(8)
            ->values();

        return response()->json([
            'source' => 'co_view',
            'data' => $recommendedProducts->map(fn (Product $product) => $this->productCardPayload($product))->values(),
        ]);
    }

    public function personalRecommendations(Request $request): JsonResponse
    {
        $sessionId = trim($request->string('session_id')->toString());

        if ($sessionId === '') {
            return response()->json([
                'source' => 'featured_fallback',
                'data' => $this->fallbackRecommendations()->map(fn (Product $product) => $this->productCardPayload($product))->values(),
            ]);
        }

        $orderedProductIds = OrderItem::query()
            ->selectRaw('product_id, SUM(qty) as score')
            ->whereNotNull('product_id')
            ->whereHas('order', function (Builder $query) use ($sessionId): void {
                $query
                    ->where('session_id', $sessionId)
                    ->whereIn('status', ['new', 'paid', 'processing', 'completed'])
                    ->where('placed_at', '>=', now()->subDays(180));
            })
            ->groupBy('product_id')
            ->orderByDesc('score')
            ->limit(24)
            ->pluck('product_id')
            ->values();

        if ($orderedProductIds->isNotEmpty()) {
            return response()->json([
                'source' => 'order_history',
                'data' => $this->personalizedByProductHistory($orderedProductIds)
                    ->map(fn (Product $product) => $this->productCardPayload($product))
                    ->values(),
            ]);
        }

        $viewedSlugs = TrackingEvent::query()
            ->where('event_name', 'view_product')
            ->where('session_id', $sessionId)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->orderByDesc('occurred_at')
            ->limit(300)
            ->get(['payload'])
            ->map(fn (TrackingEvent $event): ?string => $event->payload['slug'] ?? null)
            ->filter(fn (?string $slug): bool => is_string($slug))
            ->unique()
            ->values();

        if ($viewedSlugs->isNotEmpty()) {
            $viewedProductIds = Product::query()
                ->whereIn('slug', $viewedSlugs)
                ->pluck('id')
                ->values();

            if ($viewedProductIds->isNotEmpty()) {
                return response()->json([
                    'source' => 'view_history',
                    'data' => $this->personalizedByProductHistory($viewedProductIds)
                        ->map(fn (Product $product) => $this->productCardPayload($product))
                        ->values(),
                ]);
            }
        }

        return response()->json([
            'source' => 'featured_fallback',
            'data' => $this->fallbackRecommendations()->map(fn (Product $product) => $this->productCardPayload($product))->values(),
        ]);
    }

    /**
     * @param Collection<int, int> $seedProductIds
     * @return Collection<int, Product>
     */
    private function personalizedByProductHistory(Collection $seedProductIds): Collection
    {
        $seedIds = $seedProductIds
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($seedIds->isEmpty()) {
            return $this->fallbackRecommendations();
        }

        $categoryIds = Product::query()
            ->selectRaw('category_id, COUNT(*) as score')
            ->whereIn('id', $seedIds)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByDesc('score')
            ->limit(4)
            ->pluck('category_id')
            ->values();

        $baseQuery = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true)
            ->whereNotIn('id', $seedIds);

        if ($categoryIds->isNotEmpty()) {
            $baseQuery->whereIn('category_id', $categoryIds);
        }

        $recommendations = $baseQuery
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(12)
            ->get();

        if ($recommendations->count() >= 12) {
            return $recommendations;
        }

        $excludeIds = $recommendations->pluck('id')
            ->merge($seedIds)
            ->unique()
            ->values();

        $fill = $this->fallbackRecommendations($excludeIds, 12 - $recommendations->count());

        return $recommendations->merge($fill)->values();
    }

    /**
     * @param Collection<int, int>|null $excludeProductIds
     * @return Collection<int, Product>
     */
    private function fallbackRecommendations(?Collection $excludeProductIds = null, int $limit = 12): Collection
    {
        $query = Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,url,alt,is_cover,sort_order'])
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit($limit);

        if ($excludeProductIds && $excludeProductIds->isNotEmpty()) {
            $query->whereNotIn('id', $excludeProductIds);
        }

        return $query->get()->values();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     price: float,
     *     old_price: float|null,
     *     currency: string,
     *     stock: int,
     *     tags: array<int, array{code: string, label: string}>,
     *     category: array{name: string, slug: string}|null,
     *     image_url: string|null
     * }
     */
    private function productCardPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
            'currency' => $product->currency,
            'stock' => $product->stock,
            'tags' => $this->resolveProductTags($product),
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
        ];
    }
}
