<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class YandexFeedController extends Controller
{
    private const CACHE_KEY = 'shoria:feed:yandex:yml';
    private const CACHE_TTL = 14400; // 4 часа

    public function feed(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->buildXml());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function refresh(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_CONTENT_MANAGER], true)) {
            abort(403, 'Недостаточно прав.');
        }

        Cache::forget(self::CACHE_KEY);

        return response()->json(['ok' => true, 'message' => 'YML-фид сброшен, будет перестроен при следующем запросе.']);
    }

    private function buildXml(): string
    {
        $settings = SiteSetting::current()->mergedFeedSettings();

        $shopName = $settings['shop_name'] ?: config('app.name', 'Shoria');
        $companyName = $settings['company_name'] ?: $shopName;
        $currency = $settings['currency'] ?? 'RUB';
        $salesNotes = $settings['sales_notes'] ?? '';
        $enableOldprice = (bool) ($settings['enable_oldprice'] ?? true);
        $maxImages = max(1, min(10, (int) ($settings['max_images'] ?? 10)));
        $minPrice = (float) ($settings['min_price'] ?? 0);
        $includeOutOfStock = (bool) ($settings['include_out_of_stock'] ?? false);
        $includedCategoryIds = $settings['included_category_ids']; // null = все
        $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $frontendUrl = rtrim((string) config('app.frontend_url', $appUrl), '/');
        $date = now()->format('Y-m-d H:i');

        $allCategories = Category::query()
            ->select(['id', 'name', 'parent_id'])
            ->orderBy('id')
            ->get();

        // Если задан список включённых — резолвим с учётом дочерних
        if (is_array($includedCategoryIds) && count($includedCategoryIds) > 0) {
            $allowedIds = $this->resolveIncludedCategoryIds($allCategories, $includedCategoryIds);
        } else {
            $allowedIds = null; // все
        }

        $categories = $allowedIds === null
            ? $allCategories
            : $allCategories->filter(fn ($c) => in_array($c->id, $allowedIds));

        // Товары
        $query = Product::query()
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')->limit(10), 'category'])
            ->where('is_active', true)
            ->whereNotNull('category_id');

        if ($allowedIds !== null) {
            $query->whereIn('category_id', $allowedIds);
        }

        if (! $includeOutOfStock) {
            $query->where('stock', '>', 0);
        }

        if ($minPrice > 0) {
            $query->where('price', '>=', $minPrice);
        }

        $products = $query->orderBy('id')->get();

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE yml_catalog SYSTEM "shops.dtd">'
            .'<yml_catalog date="'.$date.'"><shop></shop></yml_catalog>'
        );

        $shop = $xml->shop;
        $shop->addChild('name', htmlspecialchars($shopName));
        $shop->addChild('company', htmlspecialchars($companyName));
        $shop->addChild('url', $frontendUrl);

        // Валюты
        $currencies = $shop->addChild('currencies');
        $cur = $currencies->addChild('currency');
        $cur->addAttribute('id', $currency);
        $cur->addAttribute('rate', '1');

        // Категории
        $categoriesNode = $shop->addChild('categories');
        foreach ($categories as $cat) {
            $catNode = $categoriesNode->addChild('category', htmlspecialchars((string) $cat->name));
            $catNode->addAttribute('id', (string) $cat->id);
            if ($cat->parent_id && ($allowedIds === null || in_array($cat->parent_id, $allowedIds))) {
                $catNode->addAttribute('parentId', (string) $cat->parent_id);
            }
        }

        // Офферы
        $offersNode = $shop->addChild('offers');
        foreach ($products as $product) {
            $offer = $offersNode->addChild('offer');
            $offer->addAttribute('id', (string) $product->id);
            $offer->addAttribute('available', $product->stock > 0 ? 'true' : 'false');

            $offer->addChild('url', $frontendUrl.'/product/'.$product->slug);
            $offer->addChild('price', (string) (int) $product->price);

            if ($enableOldprice && $product->old_price && $product->old_price > $product->price) {
                $offer->addChild('oldprice', (string) (int) $product->old_price);
            }

            $offer->addChild('currencyId', $currency);
            $offer->addChild('categoryId', (string) $product->category_id);

            foreach ($product->images->take($maxImages) as $image) {
                $imgUrl = $image->url;
                if ($imgUrl) {
                    if (! str_starts_with($imgUrl, 'http')) {
                        $imgUrl = $appUrl.'/'.ltrim($imgUrl, '/');
                    }
                    $offer->addChild('picture', htmlspecialchars($imgUrl));
                }
            }

            $offer->addChild('name', htmlspecialchars((string) $product->name));

            if ($product->brand) {
                $offer->addChild('vendor', htmlspecialchars((string) $product->brand));
            }

            if ($product->description) {
                $desc = mb_substr(strip_tags((string) $product->description), 0, 3000);
                $offer->addChild('description', htmlspecialchars($desc));
            }

            if ($salesNotes) {
                $offer->addChild('sales_notes', htmlspecialchars($salesNotes));
            }
        }

        $rawXml = $xml->asXML();

        return $rawXml !== false ? $rawXml : '';
    }

    /**
     * Возвращает ID включённых категорий вместе со всеми их потомками.
     * Если выбрана родительская категория — все дочерние тоже включаются.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Category>  $allCategories
     * @param  int[]  $rootIncluded
     * @return int[]
     */
    private function resolveIncludedCategoryIds(\Illuminate\Support\Collection $allCategories, array $rootIncluded): array
    {
        $included = $rootIncluded;
        $changed = true;

        while ($changed) {
            $changed = false;
            foreach ($allCategories as $cat) {
                if (! in_array($cat->id, $included) && in_array($cat->parent_id, $included)) {
                    $included[] = $cat->id;
                    $changed = true;
                }
            }
        }

        return array_unique($included);
    }
}
