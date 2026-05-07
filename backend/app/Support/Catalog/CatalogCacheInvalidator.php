<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Cache;

final class CatalogCacheInvalidator
{
    /**
     * Сбрасывает HTTP-кэш каталога: в ключах участвует revision (см. CatalogCacheKeys).
     */
    public static function bump(): void
    {
        $key = CatalogCacheKeys::revisionStorageKey();

        try {
            $newValue = Cache::increment($key);
            if ($newValue !== false) {
                return;
            }
        } catch (\Throwable) {
            // драйвер без increment или ошибка — fallback
        }

        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addYears(30));
    }
}
