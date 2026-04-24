<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TrackingEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->collectCatalogFilters($request);

        $query = $this->catalogQuery($filters)
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
            ]);

        $this->applySort($query, $filters['sort']);

        $products = $query
            ->paginate(12)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $this->resolveBrandName($product),
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
                'currency' => $product->currency,
                'stock' => $product->stock,
                'tags' => $this->resolveProductTags($product),
                'category' => $this->resolvePrimaryCategoryPayload($product),
                'image_url' => $product->images
                    ->sortBy([
                        ['is_cover', 'desc'],
                        ['sort_order', 'asc'],
                    ])
                    ->first()?->url,
            ]);

        $products->appends($request->query());

        return response()->json([
            ...$products->toArray(),
            'filters' => $this->resolveCatalogFacets($filters),
        ]);
    }

    /**
     * @return array{
     *     category: string,
     *     q: string,
     *     sort: string,
     *     price_min: float|null,
     *     price_max: float|null,
     *     in_stock: bool,
     *     on_sale: bool,
     *     tags: array<int, string>,
     *     brands: array<int, string>,
     *     colors: array<int, string>,
     *     sizes: array<int, string>
     * }
     */
    private function collectCatalogFilters(Request $request): array
    {
        return [
            'category' => trim($request->string('category')->toString()),
            'q' => trim($request->string('q')->toString()),
            'sort' => $request->string('sort')->toString(),
            'price_min' => is_numeric($request->query('price_min')) ? (float) $request->query('price_min') : null,
            'price_max' => is_numeric($request->query('price_max')) ? (float) $request->query('price_max') : null,
            'in_stock' => filter_var($request->query('in_stock', false), FILTER_VALIDATE_BOOLEAN),
            'on_sale' => filter_var($request->query('on_sale', false), FILTER_VALIDATE_BOOLEAN),
            'tags' => $this->parseTagFilters($request),
            'brands' => $this->parseStringListFilter($request->query('brands')),
            'colors' => $this->parseStringListFilter($request->query('colors')),
            'sizes' => $this->parseStringListFilter($request->query('sizes')),
        ];
    }

    /**
     * @param array{
     *     category: string,
     *     q: string,
     *     sort: string,
     *     price_min: float|null,
     *     price_max: float|null,
     *     in_stock: bool,
     *     on_sale: bool,
     *     tags: array<int, string>,
     *     brands: array<int, string>,
     *     colors: array<int, string>,
     *     sizes: array<int, string>
     * } $filters
     * @param array<int, string> $exclude
     */
    private function catalogQuery(array $filters, array $exclude = []): Builder
    {
        $excludeMap = array_fill_keys($exclude, true);
        $query = Product::query()->where('products.is_active', true);

        if (! isset($excludeMap['category']) && $filters['category'] !== '') {
            $categoryIds = Category::query()
                ->where('is_active', true)
                ->where(function (Builder $categoryQuery) use ($filters): void {
                    $categoryQuery
                        ->where('slug', $filters['category'])
                        ->orWhereHas('parent', fn (Builder $parentQuery) => $parentQuery->where('slug', $filters['category']));
                })
                ->pluck('id');

            $query->where(function (Builder $categoryQuery) use ($categoryIds): void {
                $categoryQuery
                    ->whereIn('category_id', $categoryIds)
                    ->orWhereHas('categories', fn (Builder $linkedQuery) => $linkedQuery->whereIn('categories.id', $categoryIds));
            });
        }

        if (! isset($excludeMap['q']) && $filters['q'] !== '') {
            $searchVariants = $this->buildSearchVariants($filters['q']);

            $query->where(function ($searchQuery) use ($searchVariants): void {
                foreach ($searchVariants as $index => $variant) {
                    $needle = '%' . $variant . '%';
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $searchQuery->{$method}(function ($variantQuery) use ($needle): void {
                        $variantQuery
                            ->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(products.brand) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(products.sku) LIKE ?', [$needle])
                            ->orWhereHas('category', function ($categoryQuery) use ($needle): void {
                                $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                            })
                            ->orWhereHas('categories', function ($categoryQuery) use ($needle): void {
                                $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                            });
                    });
                }
            });
        }

        if (! isset($excludeMap['price']) && $filters['price_min'] !== null) {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if (! isset($excludeMap['price']) && $filters['price_max'] !== null) {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        if (! isset($excludeMap['in_stock']) && $filters['in_stock']) {
            $query->where('products.stock', '>', 0);
        }

        if (! isset($excludeMap['on_sale']) && $filters['on_sale']) {
            $query->whereNotNull('products.old_price')
                ->whereColumn('products.old_price', '>', 'products.price');
        }

        if (! isset($excludeMap['tags']) && $filters['tags'] !== []) {
            $query->where(function (Builder $tagQuery) use ($filters): void {
                foreach ($filters['tags'] as $index => $tag) {
                    $column = $this->tagToColumn($tag);

                    if ($column === null) {
                        continue;
                    }

                    $method = $index === 0 ? 'where' : 'orWhere';
                    $tagQuery->{$method}("products.{$column}", true);
                }
            });
        }

        if (! isset($excludeMap['brands']) && $filters['brands'] !== []) {
            $query->where(function (Builder $brandQuery) use ($filters): void {
                foreach ($filters['brands'] as $index => $brand) {
                    $needle = mb_strtolower($brand);
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $brandQuery->{$method}(function (Builder $singleBrandQuery) use ($needle): void {
                        $singleBrandQuery
                            ->whereRaw('LOWER(brand) = ?', [$needle])
                            ->orWhereHas('brandEntity', fn (Builder $brandEntityQuery) => $brandEntityQuery->whereRaw('LOWER(name) = ?', [$needle]));
                    });
                }
            });
        }

        if (! isset($excludeMap['colors']) && $filters['colors'] !== []) {
            $query->whereHas('variants', function (Builder $variantQuery) use ($filters): void {
                $variantQuery
                    ->where('is_active', true)
                    ->where(function (Builder $colorQuery) use ($filters): void {
                        foreach ($filters['colors'] as $index => $color) {
                            $needle = mb_strtolower($color);
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $colorQuery->{$method}('LOWER(color_label) = ?', [$needle]);
                        }
                    });
            });
        }

        if (! isset($excludeMap['sizes']) && $filters['sizes'] !== []) {
            $query->whereHas('variants', function (Builder $variantQuery) use ($filters): void {
                $variantQuery
                    ->where('is_active', true)
                    ->where(function (Builder $sizeQuery) use ($filters): void {
                        foreach ($filters['sizes'] as $index => $size) {
                            $needle = mb_strtolower($size);
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $sizeQuery->{$method}('LOWER(size_label) = ?', [$needle]);
                        }
                    });
            });
        }

        return $query;
    }

    /**
     * @param array{
     *     category: string,
     *     q: string,
     *     sort: string,
     *     price_min: float|null,
     *     price_max: float|null,
     *     in_stock: bool,
     *     on_sale: bool,
     *     tags: array<int, string>,
     *     brands: array<int, string>,
     *     colors: array<int, string>,
     *     sizes: array<int, string>
     * } $filters
     * @return array{
     *     categories: array<int, array{slug: string, count: int}>,
     *     tags: array<int, array{code: string, label: string, count: int}>,
     *     brands: array<int, array{value: string, count: int}>,
     *     colors: array<int, array{value: string, count: int}>,
     *     sizes: array<int, array{value: string, count: int}>,
     *     on_sale: array{count: int}
     * }
     */
    private function resolveCatalogFacets(array $filters): array
    {
        $categoryFacetBase = $this->catalogQuery($filters, ['category']);
        $categoryBaseSql = (clone $categoryFacetBase)
            ->select('products.id')
            ->toBase();

        $primaryCategorySql = DB::table('products')
            ->selectRaw('products.id as product_id, products.category_id as category_id')
            ->whereNotNull('products.category_id');

        $linkedCategorySql = DB::table('category_product')
            ->selectRaw('category_product.product_id as product_id, category_product.category_id as category_id');

        $unionCategorySql = $primaryCategorySql->union($linkedCategorySql);

        $categoryCountsRows = DB::query()
            ->fromSub($categoryBaseSql, 'base_products')
            ->joinSub($unionCategorySql, 'product_categories', function ($join): void {
                $join->on('product_categories.product_id', '=', 'base_products.id');
            })
            ->selectRaw('product_categories.category_id as category_id, COUNT(DISTINCT base_products.id) as aggregate')
            ->groupBy('product_categories.category_id')
            ->get();

        $categoryCounts = $categoryCountsRows
            ->pluck('aggregate', 'category_id')
            ->map(fn ($count): int => (int) $count);

        $categories = Category::query()
            ->where('is_active', true)
            ->pluck('slug', 'id');

        $tagFacetBase = $this->catalogQuery($filters, ['tags']);
        $tagBlueprints = [
            ['code' => 'hit', 'label' => 'Хит', 'column' => 'is_hit'],
            ['code' => 'new', 'label' => 'Новинка', 'column' => 'is_new'],
            ['code' => 'customer_choice', 'label' => 'Выбор покупателей', 'column' => 'is_customer_choice'],
        ];

        $tags = collect($tagBlueprints)
            ->map(function (array $tag) use ($tagFacetBase): array {
                $count = (clone $tagFacetBase)->where($tag['column'], true)->count();

                return [
                    'code' => $tag['code'],
                    'label' => $tag['label'],
                    'count' => $count,
                ];
            })
            ->filter(fn (array $tag): bool => $tag['count'] > 0 || in_array($tag['code'], $filters['tags'], true))
            ->values();

        $brandFacetBase = $this->catalogQuery($filters, ['brands']);
        $brands = (clone $brandFacetBase)
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->selectRaw('COALESCE(brands.name, products.brand) as value, COUNT(*) as count')
            ->whereRaw("COALESCE(brands.name, products.brand) IS NOT NULL")
            ->whereRaw("COALESCE(brands.name, products.brand) != ''")
            ->groupByRaw('COALESCE(brands.name, products.brand)')
            ->orderByRaw('COALESCE(brands.name, products.brand)')
            ->get()
            ->map(fn ($row): array => [
                'value' => (string) $row->value,
                'count' => (int) $row->count,
            ])
            ->values();

        $colorFacetBase = $this->catalogQuery($filters, ['colors']);
        $colors = ProductVariant::query()
            ->selectRaw('product_variants.color_label as value, COUNT(DISTINCT product_variants.product_id) as count')
            ->where('product_variants.is_active', true)
            ->whereNotNull('product_variants.color_label')
            ->where('product_variants.color_label', '!=', '')
            ->whereIn('product_variants.product_id', (clone $colorFacetBase)->select('products.id'))
            ->groupBy('product_variants.color_label')
            ->orderBy('product_variants.color_label')
            ->get()
            ->map(fn ($row): array => [
                'value' => (string) $row->value,
                'count' => (int) $row->count,
            ])
            ->values();

        $sizeFacetBase = $this->catalogQuery($filters, ['sizes']);
        $sizes = ProductVariant::query()
            ->selectRaw('product_variants.size_label as value, COUNT(DISTINCT product_variants.product_id) as count')
            ->where('product_variants.is_active', true)
            ->whereNotNull('product_variants.size_label')
            ->where('product_variants.size_label', '!=', '')
            ->whereIn('product_variants.product_id', (clone $sizeFacetBase)->select('products.id'))
            ->groupBy('product_variants.size_label')
            ->orderBy('product_variants.size_label')
            ->get()
            ->map(fn ($row): array => [
                'value' => (string) $row->value,
                'count' => (int) $row->count,
            ])
            ->values();

        $saleFacetBase = $this->catalogQuery($filters, ['on_sale']);
        $onSaleCount = (clone $saleFacetBase)
            ->whereNotNull('products.old_price')
            ->whereColumn('products.old_price', '>', 'products.price')
            ->count();

        return [
            'categories' => $categoryCounts
                ->map(function ($count, $categoryId) use ($categories): ?array {
                    $slug = $categories[(int) $categoryId] ?? null;

                    if (! $slug) {
                        return null;
                    }

                    return [
                        'slug' => $slug,
                        'count' => (int) $count,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
            'tags' => $tags->all(),
            'brands' => $brands->all(),
            'colors' => $colors->all(),
            'sizes' => $sizes->all(),
            'on_sale' => [
                'count' => $onSaleCount,
            ],
        ];
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('products.price')->orderBy('products.sort_order'),
            'price_desc' => $query->orderByDesc('products.price')->orderBy('products.sort_order'),
            'name_asc' => $query->orderBy('products.name')->orderBy('products.sort_order'),
            'name_desc' => $query->orderByDesc('products.name')->orderBy('products.sort_order'),
            default => $query->orderByDesc('products.is_featured')->orderBy('products.sort_order'),
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
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
            ])
            ->where('is_active', true)
            ->where(function ($searchQuery) use ($searchVariants): void {
                foreach ($searchVariants as $index => $variant) {
                    $needle = '%' . $variant . '%';

                    $method = $index === 0 ? 'where' : 'orWhere';

                    $searchQuery->{$method}(function ($variantQuery) use ($needle): void {
                        $variantQuery
                            ->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(products.brand) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(products.sku) LIKE ?', [$needle])
                            ->orWhereHas('category', function ($categoryQuery) use ($needle): void {
                                $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                            })
                            ->orWhereHas('categories', function ($categoryQuery) use ($needle): void {
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
                'brand' => $this->resolveBrandName($product),
                'slug' => $product->slug,
                'price' => (float) $product->price,
                'currency' => $product->currency,
                'tags' => $this->resolveProductTags($product),
                'category' => $this->resolvePrimaryCategoryPayload($product),
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
        $items = $this->parseStringListFilter($request->query('tags'));

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

    /**
     * @param mixed $rawValues
     * @return array<int, string>
     */
    private function parseStringListFilter(mixed $rawValues): array
    {
        if (is_string($rawValues)) {
            $items = explode(',', $rawValues);
        } elseif (is_array($rawValues)) {
            $items = $rawValues;
        } else {
            $items = [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $items,
        ))));
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

    public function show(Request $request, string $slug): JsonResponse
    {
        $selectedVariantSlug = $request->string('variant')->toString();

        $product = Product::query()
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
                'variants:id,product_id,slug,size_label,color_label,sku,price,stock,is_active,sort_order',
                'variants.images:id,product_variant_id,url,alt,is_cover,sort_order',
            ])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $activeVariants = $product->variants
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();

        $selectedVariant = null;

        if ($selectedVariantSlug !== '') {
            $selectedVariant = $activeVariants->firstWhere('slug', $selectedVariantSlug);
        }

        if (! $selectedVariant) {
            $selectedVariant = $activeVariants->firstWhere('stock', '>', 0) ?? $activeVariants->first();
        }

        $productImages = $product->images
            ->sortBy([
                ['is_cover', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->values();

        $activeImages = ($selectedVariant?->images?->isNotEmpty() ?? false)
            ? $selectedVariant->images
                ->sortBy([
                    ['is_cover', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->values()
            : $productImages;

        $variants = $activeVariants
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'slug' => $variant->slug,
                'size_label' => $variant->size_label,
                'color_label' => $variant->color_label,
                'sku' => $variant->sku,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'stock' => $variant->stock,
                'images' => $variant->images
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
                'has_custom_images' => $variant->images->isNotEmpty(),
            ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $this->resolveBrandName($product),
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
            'selected_variant_slug' => $selectedVariant?->slug,
            'category' => $this->resolvePrimaryCategoryPayload($product),
            'categories' => $product->categories
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
            'variants' => $variants,
            'images' => $activeImages
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
                ->with([
                    'category:id,name,slug',
                    'categories:id,name,slug',
                    'brandEntity:id,name,slug',
                    'images:id,product_id,url,alt,is_cover,sort_order',
                ])
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
                ->with([
                    'category:id,name,slug',
                    'categories:id,name,slug',
                    'brandEntity:id,name,slug',
                    'images:id,product_id,url,alt,is_cover,sort_order',
                ])
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
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
            ])
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
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
            ])
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
            ->with([
                'category:id,name,slug',
                'categories:id,name,slug',
                'brandEntity:id,name,slug',
                'images:id,product_id,url,alt,is_cover,sort_order',
            ])
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
     *     brand: string|null,
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
            'brand' => $this->resolveBrandName($product),
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'old_price' => $product->old_price !== null ? (float) $product->old_price : null,
            'currency' => $product->currency,
            'stock' => $product->stock,
            'tags' => $this->resolveProductTags($product),
            'category' => $this->resolvePrimaryCategoryPayload($product),
            'image_url' => $product->images
                ->sortBy([
                    ['is_cover', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->first()?->url,
        ];
    }

    private function resolveBrandName(Product $product): ?string
    {
        return $product->brandEntity?->name
            ?? ($product->brand !== null && trim($product->brand) !== '' ? $product->brand : null);
    }

    /**
     * @return array{name: string, slug: string}|null
     */
    private function resolvePrimaryCategoryPayload(Product $product): ?array
    {
        $category = $product->category ?? $product->categories->sortBy('name')->first();

        if (! $category) {
            return null;
        }

        return [
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }
}
