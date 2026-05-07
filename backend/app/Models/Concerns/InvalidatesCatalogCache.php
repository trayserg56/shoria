<?php

namespace App\Models\Concerns;

use App\Support\Catalog\CatalogCacheInvalidator;

trait InvalidatesCatalogCache
{
    protected static function bootInvalidatesCatalogCache(): void
    {
        static::saved(static function (): void {
            CatalogCacheInvalidator::bump();
        });

        static::deleted(static function (): void {
            CatalogCacheInvalidator::bump();
        });
    }
}
