<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\NewsPost;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ServicePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SpaServerMetaResolver
{
    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    public function resolve(Request $request): array
    {
        $path = trim($request->path(), '/');
        $base = rtrim((string) config('app.url'), '/');

        if ($path === '') {
            return $this->home($base);
        }

        if (str_starts_with($path, 'product/')) {
            $tail = substr($path, strlen('product/'));
            $resolved = $this->resolveProductMeta($base, $tail, $request);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (str_starts_with($path, 'catalog')) {
            return $this->catalog($base, $path, $request);
        }

        if ($path === 'news' || str_starts_with($path, 'news/')) {
            return $this->news($base, $path, $request);
        }

        if (str_starts_with($path, 'pages/')) {
            $slug = substr($path, strlen('pages/'));

            return $this->servicePage($base, $slug);
        }

        return $this->staticRoute($base, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationJsonLdNode(string $base): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Shoria',
            'url' => $base.'/',
        ];
    }

    /**
     * Sitelinks search box: каталог с текстовым параметром {@see CatalogView} <code>q</code>.
     *
     * @return array<string, mixed>
     */
    private function webSiteJsonLdNode(string $base): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Shoria',
            'url' => $base.'/',
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Shoria',
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $base.'/catalog?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function home(string $base): array
    {
        $title = 'Shoria — интернет-магазин';
        $description = 'Shoria: каталог товаров, рекомендации и удобный checkout.';
        $canonical = $base.'/';

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => null,
            'og_type' => 'website',
            'json_ld' => [
                $this->organizationJsonLdNode($base),
                $this->webSiteJsonLdNode($base),
            ],
        ];
    }

    /**
     * @return null|array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function resolveProductMeta(string $base, string $tail, Request $request): ?array
    {
        $segments = array_values(array_filter(explode('/', $tail), static fn (string $s): bool => $s !== ''));

        if ($segments === []) {
            return null;
        }

        $product = $this->lookupProductFromSegments($segments);

        if (! $product instanceof Product) {
            return [
                'title' => 'Страница не найдена — Shoria',
                'description' => 'Запрошенная страница не найдена.',
                'robots' => 'noindex,nofollow',
                'canonical' => $base.'/'.'product/'.implode('/', array_map('rawurlencode', $segments)),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        $product->loadMissing(['category:id,name,slug,parent_id', 'categories:id,name,slug,parent_id', 'brandEntity:id,name', 'images']);

        $category = $product->category ?? $product->categories->sortBy('name')->first();

        $canonicalPath = $category?->slug
            ? '/product/'.$category->slug.'/'.$product->slug
            : '/product/'.$product->slug;

        $canonical = $base.$canonicalPath;

        $title = trim((string) $product->seo_title) !== ''
            ? trim((string) $product->seo_title)
            : $this->defaultProductTitle($product);
        $description = $this->productSeoDescription($product);

        $imageUrl = $product->images
            ->sortBy([
                ['is_cover', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->first()?->url;

        $brandName = $this->resolveBrandName($product) ?? 'Shoria';

        $jsonLd = [
            $this->buildProductJsonLd($product, $canonical, $imageUrl, $brandName, $description),
            $this->buildBreadcrumbJsonLd($base, [
                ['name' => 'Главная', 'path' => '/'],
                ['name' => 'Каталог', 'path' => '/catalog'],
                ...$this->categoryBreadcrumbItems($category),
                ['name' => $product->name, 'path' => parse_url($canonical, PHP_URL_PATH) ?: $canonicalPath],
            ]),
        ];

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => $imageUrl,
            'og_type' => 'website',
            'json_ld' => array_values(array_filter($jsonLd)),
        ];
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function lookupProductFromSegments(array $segments): ?Product
    {
        $n = count($segments);

        if ($n === 1) {
            return Product::query()
                ->where('is_active', true)
                ->where('slug', $segments[0])
                ->first();
        }

        if ($n === 2) {
            $category = Category::query()
                ->where('is_active', true)
                ->where('slug', $segments[0])
                ->first();

            if ($category instanceof Category) {
                $product = Product::query()
                    ->where('is_active', true)
                    ->where('slug', $segments[1])
                    ->where(function (Builder $q) use ($category): void {
                        $q->where('category_id', $category->id)
                            ->orWhereHas('categories', fn (Builder $l) => $l->where('categories.id', $category->id));
                    })
                    ->first();

                if ($product instanceof Product) {
                    return $product;
                }
            }

            return Product::query()
                ->where('is_active', true)
                ->where('slug', $segments[0])
                ->first();
        }

        $category = Category::query()
            ->where('is_active', true)
            ->where('slug', $segments[0])
            ->first();

        if (! $category instanceof Category) {
            return null;
        }

        return Product::query()
            ->where('is_active', true)
            ->where('slug', $segments[1])
            ->where(function (Builder $q) use ($category): void {
                $q->where('category_id', $category->id)
                    ->orWhereHas('categories', fn (Builder $l) => $l->where('categories.id', $category->id));
            })
            ->first();
    }

    private function resolveBrandName(Product $product): ?string
    {
        return $product->brandEntity?->name
            ?? ($product->brand !== null && trim((string) $product->brand) !== '' ? trim((string) $product->brand) : null);
    }

    /** Title without seo_title — как во фронте {@see ProductView.vue}. */
    private function defaultProductTitle(Product $product): string
    {
        $category = $product->category ?? $product->categories->sortBy('name')->first();
        $categoryPart = $category ? $category->name.' · ' : '';

        return $product->name.' — '.$categoryPart.'Shoria';
    }

    /** Meta/OG description — как во фронте {@see ProductView.vue}. */
    private function productSeoDescription(Product $product): string
    {
        if (trim((string) $product->seo_description) !== '') {
            return trim((string) $product->seo_description);
        }

        $raw = $product->description;
        if (is_string($raw) && trim(strip_tags($raw)) !== '') {
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

            return $this->truncate($t, 160);
        }

        return "Купить {$product->name} в Shoria: актуальная цена, наличие и рекомендации.";
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductJsonLd(
        Product $product,
        string $canonical,
        ?string $imageUrl,
        string $brandName,
        string $description,
    ): array {
        $availability = $product->stock > 0 ? 'InStock' : 'OutOfStock';
        $currency = $product->currency ?: 'RUB';
        $price = number_format((float) $product->price, 2, '.', '');

        $primaryCategory = $product->category ?? $product->categories->sortBy('name')->first();

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $description,
            'sku' => $product->sku ?: null,
            'image' => $imageUrl !== null && $imageUrl !== '' ? [$imageUrl] : null,
            'category' => $primaryCategory?->name,
            'brand' => [
                '@type' => 'Brand',
                'name' => $brandName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => $price,
                'availability' => 'https://schema.org/'.$availability,
                'url' => $canonical,
            ],
        ];

        $count = (int) ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->count();

        if ($count > 0) {
            $avg = round((float) ProductReview::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->avg('rating'), 1);
            $payload['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $avg,
                'reviewCount' => $count,
            ];
        }

        return array_filter($payload, static fn ($v) => $v !== null);
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $items
     * @return array<string, mixed>
     */
    private function buildBreadcrumbJsonLd(string $base, array $items): array
    {
        $elements = [];

        foreach ($items as $index => $item) {
            $path = $item['path'];
            $url = str_starts_with($path, 'http') ? $path : $base.(str_starts_with($path, '/') ? '' : '/').ltrim($path, '/');

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $url,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    private function categoryBreadcrumbItems(?Category $leaf): array
    {
        if (! $leaf instanceof Category) {
            return [];
        }

        $chain = [];
        $current = $leaf;

        while ($current instanceof Category) {
            $chain[] = $current;
            if ($current->parent_id === null) {
                break;
            }

            $current = Category::query()
                ->where('id', $current->parent_id)
                ->where('is_active', true)
                ->first();
        }

        $chain = array_reverse($chain);
        $segments = [];
        $items = [];

        foreach ($chain as $cat) {
            $segments[] = $cat->slug;
            $catalogPath = '/catalog/'.implode('/', array_map('rawurlencode', $segments));
            $items[] = ['name' => $cat->name, 'path' => $catalogPath];
        }

        return $items;
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function catalog(string $base, string $path, Request $request): array
    {
        if ($path === 'catalog') {
            return [
                'title' => 'Каталог — Shoria',
                'description' => 'Категории и товары Shoria: удобный выбор, фильтры, бренды и быстрый переход к карточкам.',
                'robots' => 'index,follow',
                'canonical' => $base.'/catalog',
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [
                    $this->buildBreadcrumbJsonLd($base, [
                        ['name' => 'Главная', 'path' => '/'],
                        ['name' => 'Каталог', 'path' => '/catalog'],
                    ]),
                ],
            ];
        }

        $rest = substr($path, strlen('catalog/'));
        $slugs = array_values(array_filter(explode('/', $rest), static fn (string $s): bool => $s !== ''));

        $deepest = $this->resolveCategoryChain($slugs);

        if (! $deepest instanceof Category) {
            return [
                'title' => 'Каталог — Shoria',
                'description' => 'Категории и товары Shoria: удобный выбор, фильтры, бренды и быстрый переход к карточкам.',
                'robots' => 'noindex,follow',
                'canonical' => $base.'/catalog',
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        $catalogPath = '/catalog/'.implode('/', array_map('rawurlencode', $slugs));

        $page = max(1, (int) $request->query('page', 1));
        $canonical = $base.$catalogPath;

        if ($page > 1) {
            $canonical .= '?page='.$page;
        }

        $titleBase = trim((string) $deepest->seo_title) !== ''
            ? trim((string) $deepest->seo_title)
            : $deepest->name.' — каталог Shoria';
        $title = $page > 1 ? $titleBase.' · Страница '.$page : $titleBase;

        $description = trim((string) $deepest->seo_description) !== ''
            ? trim((string) $deepest->seo_description)
            : "Подборка товаров Shoria в категории {$deepest->name}: фильтры, поиск и быстрый выбор.";

        $crumbs = [['name' => 'Главная', 'path' => '/'], ['name' => 'Каталог', 'path' => '/catalog']];
        $acc = [];

        foreach ($slugs as $slug) {
            $acc[] = $slug;
            $c = $this->resolveCategoryChain($acc);

            if ($c instanceof Category) {
                $p = '/catalog/'.implode('/', array_map('rawurlencode', $acc));
                $crumbs[] = ['name' => $c->name, 'path' => $p];
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => null,
            'og_type' => 'website',
            'json_ld' => [$this->buildBreadcrumbJsonLd($base, $crumbs)],
        ];
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function resolveCategoryChain(array $slugs): ?Category
    {
        if ($slugs === []) {
            return null;
        }

        $parentId = null;
        $current = null;

        foreach ($slugs as $slug) {
            $q = Category::query()
                ->where('slug', $slug)
                ->where('is_active', true);

            if ($parentId === null) {
                $q->whereNull('parent_id');
            } else {
                $q->where('parent_id', $parentId);
            }

            $current = $q->first();

            if (! $current instanceof Category) {
                return null;
            }

            $parentId = $current->id;
        }

        return $current;
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function news(string $base, string $path, Request $request): array
    {
        if ($path === 'news') {
            $page = max(1, (int) $request->query('page', 1));
            $rawType = $request->query('type');
            $type = is_string($rawType) && in_array($rawType, ['news', 'guide', 'collection', 'promo'], true)
                ? $rawType
                : null;

            return $this->newsListMeta($base, $page, $type);
        }

        $slug = substr($path, strlen('news/'));
        $post = NewsPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->first();

        if (! $post instanceof NewsPost) {
            return [
                'title' => 'Страница не найдена — Shoria',
                'description' => 'Запрошенная страница не найдена.',
                'robots' => 'noindex,nofollow',
                'canonical' => $base.'/news/'.rawurlencode($slug),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        $title = trim((string) $post->seo_title) !== ''
            ? trim((string) $post->seo_title)
            : $post->title.' — новости Shoria';
        $description = trim((string) $post->seo_description) !== ''
            ? trim((string) $post->seo_description)
            : ($post->excerpt !== null ? $this->truncate(trim(strip_tags($post->excerpt)), 160) : 'Материал из блога Shoria о товарах, трендах и полезных подборках.');
        $canonical = $base.'/news/'.$post->slug;

        $published = $post->published_at?->toIso8601String() ?? '';

        $schemaType = match ($post->content_type) {
            'guide', 'promo' => 'Article',
            'collection' => 'CollectionPage',
            default => 'NewsArticle',
        };

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => $schemaType,
                'headline' => $post->title,
                'description' => $description,
                'datePublished' => $published,
                'dateModified' => $published,
                'image' => $post->cover_url ? [$post->cover_url] : null,
                'mainEntityOfPage' => $canonical,
                'author' => ['@type' => 'Organization', 'name' => 'Shoria'],
                'publisher' => ['@type' => 'Organization', 'name' => 'Shoria'],
            ],
            $this->buildBreadcrumbJsonLd($base, [
                ['name' => 'Главная', 'path' => '/'],
                ['name' => 'Новости', 'path' => '/news'],
                ['name' => $post->title, 'path' => '/news/'.$post->slug],
            ]),
        ];

        $article = $jsonLd[0];
        $article = array_filter($article, static fn ($v) => $v !== null);
        $jsonLd[0] = $article;

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => $post->cover_url,
            'og_type' => 'article',
            'json_ld' => $jsonLd,
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function newsListMeta(string $base, int $page, ?string $type): array
    {
        $section = $this->newsTypeSection($type);
        $baseTitle = "{$section} — Shoria";
        $title = $page > 1 ? "{$baseTitle} · Страница {$page}" : $baseTitle;
        $sectionLower = mb_strtolower($section);

        $description = $page > 1
            ? "Архив раздела «{$section}» в Shoria: страница {$page}. Актуальные {$sectionLower}, идеи и полезные материалы."
            : "{$section} Shoria: актуальные материалы, идеи и практические рекомендации.";

        $params = [];

        if ($type !== null && $this->newsTypeSection($type) !== $this->newsTypeSection(null)) {
            $params['type'] = $type;
        }

        if ($page > 1) {
            $params['page'] = (string) $page;
        }

        $canonical = $base.'/news'.($params !== [] ? '?'.http_build_query($params) : '');

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => null,
            'og_type' => 'website',
            'json_ld' => [],
        ];
    }

    private function newsTypeSection(?string $type): string
    {
        return match ($type) {
            'guide' => 'Гайды',
            'collection' => 'Подборки',
            'promo' => 'Промо',
            default => 'Новости',
        };
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function servicePage(string $base, string $slug): array
    {
        $page = ServicePage::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page instanceof ServicePage) {
            return [
                'title' => 'Страница не найдена — Shoria',
                'description' => 'Служебная страница не найдена.',
                'robots' => 'noindex,nofollow',
                'canonical' => $base.'/pages/'.rawurlencode($slug),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        $title = trim((string) $page->seo_title) !== ''
            ? trim((string) $page->seo_title)
            : $page->title.' — Shoria';
        $description = trim((string) $page->seo_description) !== ''
            ? trim((string) $page->seo_description)
            : ($page->excerpt !== null ? $this->truncate(trim(strip_tags($page->excerpt)), 160) : 'Служебная информация интернет-магазина Shoria.');

        $canonical = $base.'/pages/'.$page->slug;

        return [
            'title' => $title,
            'description' => $description,
            'robots' => 'index,follow',
            'canonical' => $canonical,
            'og_image' => null,
            'og_type' => 'website',
            'json_ld' => [
                $this->buildBreadcrumbJsonLd($base, [
                    ['name' => 'Главная', 'path' => '/'],
                    ['name' => $page->title, 'path' => '/pages/'.$page->slug],
                ]),
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function staticRoute(string $base, string $path): array
    {
        $canonical = $base.'/'.ltrim($path, '/');

        $noindexExact = [
            'cart',
            'checkout',
            'wishlist',
            'compare',
            'account',
            'auth/oauth-callback',
        ];

        $noindexPrefix = [
            'account/',
            'order-success/',
        ];

        foreach ($noindexPrefix as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->fallbackStatic($base, $path, 'noindex,nofollow');
            }
        }

        if (in_array($path, $noindexExact, true)) {
            return $this->fallbackStatic($base, $path, 'noindex,nofollow');
        }

        $map = [
            'brands' => [
                'title' => 'Бренды — Shoria',
                'description' => 'Страница брендов магазина Shoria. Переходите в каталог выбранного бренда.',
                'robots' => 'index,follow',
            ],
            'loyalty-program' => [
                'title' => 'Программа лояльности — Shoria',
                'description' => 'Условия программы лояльности: уровни, начисления и списание баллов.',
                'robots' => 'index,follow',
            ],
        ];

        if (isset($map[$path])) {
            return [
                'title' => $map[$path]['title'],
                'description' => $map[$path]['description'],
                'robots' => $map[$path]['robots'],
                'canonical' => $canonical,
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        if ($path === 'orders') {
            return [
                'title' => 'Заказы — Shoria',
                'description' => 'История заказов и статусы покупок в кабинете Shoria.',
                'robots' => 'noindex,nofollow',
                'canonical' => $canonical,
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        return $this->fallbackStatic($base, $path, 'noindex,nofollow');
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_type: string,
     *     json_ld: array<int, array<string, mixed>>,
     * }
     */
    private function fallbackStatic(string $base, string $path, string $robots): array
    {
        $titles = [
            'cart' => ['Корзина — Shoria', 'Корзина покупок Shoria.'],
            'checkout' => ['Оформление заказа — Shoria', 'Оформление заказа Shoria: доставка и оплата.'],
            'wishlist' => ['Избранное — Shoria', 'Список избранных товаров Shoria.'],
            'compare' => ['Сравнение — Shoria', 'Сравнение товаров по ключевым параметрам.'],
            'brands' => ['Бренды — Shoria', 'Страница брендов магазина Shoria.'],
        ];

        if (str_starts_with($path, 'order-success/')) {
            return [
                'title' => 'Заказ оформлен — Shoria',
                'description' => 'Подтверждение оформления заказа.',
                'robots' => $robots,
                'canonical' => $base.'/'.ltrim($path, '/'),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        if (str_starts_with($path, 'account')) {
            return [
                'title' => 'Кабинет — Shoria',
                'description' => 'Личный кабинет покупателя Shoria.',
                'robots' => $robots,
                'canonical' => $base.'/'.ltrim($path, '/'),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        if (str_starts_with($path, 'auth/oauth-callback')) {
            return [
                'title' => 'Вход через ВКонтакте — Shoria',
                'description' => '',
                'robots' => $robots,
                'canonical' => $base.'/'.ltrim($path, '/'),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        if (isset($titles[$path])) {
            return [
                'title' => $titles[$path][0],
                'description' => $titles[$path][1],
                'robots' => $robots,
                'canonical' => $base.'/'.ltrim($path, '/'),
                'og_image' => null,
                'og_type' => 'website',
                'json_ld' => [],
            ];
        }

        return [
            'title' => 'Страница не найдена — Shoria',
            'description' => 'Запрошенная страница не найдена.',
            'robots' => $robots,
            'canonical' => $base.'/'.ltrim($path, '/'),
            'og_image' => null,
            'og_type' => 'website',
            'json_ld' => [],
        ];
    }
}
