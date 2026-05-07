<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP-кэш каталога и рекомендаций (Redis / file / database)
    |--------------------------------------------------------------------------
    | Короткие TTL: свежесть цен/остатков без ручного сброса. Отключите на отладке.
    */
    'cache_enabled' => filter_var(env('CATALOG_PERF_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'listing_ttl' => (int) env('CATALOG_CACHE_LISTING_TTL', 90),

    'suggest_ttl' => (int) env('CATALOG_CACHE_SUGGEST_TTL', 45),

    'similar_ttl' => (int) env('CATALOG_CACHE_SIMILAR_TTL', 180),

    'recommendations_ttl' => (int) env('CATALOG_CACHE_RECOMMENDATIONS_TTL', 300),

    'cart_recommendations_ttl' => (int) env('CATALOG_CACHE_CART_RECO_TTL', 120),

    'personal_recommendations_ttl' => (int) env('CATALOG_CACHE_PERSONAL_TTL', 120),
];
