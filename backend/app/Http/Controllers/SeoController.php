<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /account',
            'Disallow: /cart',
            'Disallow: /wishlist',
            'Disallow: /compare',
            'Disallow: /order-success',
            "Sitemap: {$base}/sitemap.xml",
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $staticUrls = [
            ['loc' => "{$base}/", 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => now()->toDateString()],
            ['loc' => "{$base}/catalog", 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => now()->toDateString()],
            ['loc' => "{$base}/news", 'priority' => '0.8', 'changefreq' => 'daily', 'lastmod' => now()->toDateString()],
        ];

        $productUrls = Product::query()
            ->with('category:id,slug')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get(['id', 'slug', 'category_id', 'updated_at'])
            ->map(function (Product $product) use ($base): array {
                $categorySlug = $product->category?->slug;
                $path = $categorySlug
                    ? "/product/{$categorySlug}/{$product->slug}"
                    : "/product/{$product->slug}";

                return [
                    'loc' => $base . $path,
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod' => optional($product->updated_at)->toDateString() ?? now()->toDateString(),
                ];
            })
            ->values()
            ->all();

        $newsUrls = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->map(function (NewsPost $post) use ($base): array {
                return [
                    'loc' => "{$base}/news/{$post->slug}",
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                    'lastmod' => optional($post->updated_at)->toDateString() ?? now()->toDateString(),
                ];
            })
            ->values()
            ->all();

        $urls = array_merge($staticUrls, $productUrls, $newsUrls);

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
