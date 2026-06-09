<?php

namespace App\Models\Concerns;

use App\Support\Catalog\CatalogCacheInvalidator;
use Illuminate\Support\Facades\Cache;

trait InvalidatesCatalogCache
{
    protected static function bootInvalidatesCatalogCache(): void
    {
        static::saved(static function (): void {
            CatalogCacheInvalidator::bump();
            Cache::forget('shoria:feed:yandex:yml');
        });

        static::deleted(static function (): void {
            CatalogCacheInvalidator::bump();
            Cache::forget('shoria:feed:yandex:yml');
        });
    }
}
