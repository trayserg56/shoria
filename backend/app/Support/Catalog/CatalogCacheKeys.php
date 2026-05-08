<?php

namespace App\Support\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class CatalogCacheKeys
{
    private const PREFIX = 'shoria:catalog:v1';

    public static function revisionStorageKey(): string
    {
        return self::PREFIX.':rev';
    }

    public static function listing(Request $request): string
    {
        $params = $request->query();
        self::ksortRecursive($params);
        $fingerprint = hash('sha256', json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::scopedPrefix().':listing:'.$fingerprint;
    }

    public static function suggest(string $normalizedQuery): string
    {
        return self::scopedPrefix().':suggest:'.hash('sha256', mb_strtolower(trim($normalizedQuery)));
    }

    public static function similar(string $slug): string
    {
        return self::scopedPrefix().':similar:'.$slug;
    }

    public static function recommendations(string $slug): string
    {
        return self::scopedPrefix().':reco:'.$slug;
    }

    /**
     * @param  array<int|string>  $productIds
     */
    public static function cartRecommendations(array $productIds): string
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($productIds, 'is_numeric'))));
        sort($ids);

        return self::scopedPrefix().':cart-reco:'.hash('sha256', implode(',', $ids));
    }

    public static function personal(string $sessionId): string
    {
        return self::scopedPrefix().':personal:'.hash('sha256', $sessionId);
    }

    public static function home(): string
    {
        return self::scopedPrefix().':home';
    }

    public static function categoriesTree(): string
    {
        return self::scopedPrefix().':categories-tree';
    }

    public static function navigation(): string
    {
        return self::scopedPrefix().':navigation';
    }

    public static function siteSettings(): string
    {
        return self::scopedPrefix().':site-settings';
    }

    public static function brandsList(): string
    {
        return self::scopedPrefix().':brands';
    }

    private static function scopedPrefix(): string
    {
        return self::PREFIX.':r'.self::currentRevision();
    }

    private static function currentRevision(): int
    {
        return (int) Cache::get(self::revisionStorageKey(), 0);
    }

    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }

        ksort($array);
    }
}
