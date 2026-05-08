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

    'home_ttl' => (int) env('CATALOG_CACHE_HOME_TTL', 120),

    'categories_tree_ttl' => (int) env('CATALOG_CACHE_CATEGORIES_TTL', 180),

    'navigation_ttl' => (int) env('CATALOG_CACHE_NAVIGATION_TTL', 300),

    'site_settings_ttl' => (int) env('CATALOG_CACHE_SITE_SETTINGS_TTL', 300),

    'brands_ttl' => (int) env('CATALOG_CACHE_BRANDS_TTL', 240),
];
