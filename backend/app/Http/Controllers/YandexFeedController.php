<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\CatalogCacheInvalidator;
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
        $shopName = config('app.name', 'Shoria');
        $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $frontendUrl = rtrim((string) config('app.frontend_url', $appUrl), '/');
        $currency = 'RUB';
        $date = now()->format('Y-m-d H:i');

        // Категории
        $categories = Category::query()
            ->select(['id', 'name', 'parent_id'])
            ->orderBy('id')
            ->get();

        // Товары: только активные, в наличии, с категорией
        $products = Product::query()
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')->limit(10), 'category'])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get();

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<!DOCTYPE yml_catalog SYSTEM "shops.dtd">'
            .'<yml_catalog date="'.$date.'"><shop></shop></yml_catalog>'
        );

        $shop = $xml->shop;
        $shop->addChild('name', htmlspecialchars($shopName));
        $shop->addChild('company', htmlspecialchars($shopName));
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
            if ($cat->parent_id) {
                $catNode->addAttribute('parentId', (string) $cat->parent_id);
            }
        }

        // Офферы
        $offersNode = $shop->addChild('offers');
        foreach ($products as $product) {
            $offer = $offersNode->addChild('offer');
            $offer->addAttribute('id', (string) $product->id);
            $offer->addAttribute('available', 'true');

            $offer->addChild('url', $frontendUrl.'/product/'.$product->slug);
            $offer->addChild('price', (string) (int) $product->price);
            if ($product->old_price && $product->old_price > $product->price) {
                $offer->addChild('oldprice', (string) (int) $product->old_price);
            }
            $offer->addChild('currencyId', $currency);
            $offer->addChild('categoryId', (string) $product->category_id);

            // Картинки (до 10 штук)
            foreach ($product->images->take(10) as $image) {
                $imgUrl = $image->url;
                if ($imgUrl) {
                    // Если относительный путь — делаем абсолютным
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
                $desc = strip_tags((string) $product->description);
                $desc = mb_substr($desc, 0, 3000);
                $offer->addChild('description', htmlspecialchars($desc));
            }

            $offer->addChild('sales_notes', 'Доставка по всей России');
        }

        // SimpleXMLElement не умеет DOCTYPE — собираем финальный XML вручную
        $rawXml = $xml->asXML();

        return $rawXml !== false ? $rawXml : '';
    }
}
