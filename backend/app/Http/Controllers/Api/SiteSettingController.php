<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\Catalog\CatalogCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SiteSettingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! config('catalog_performance.cache_enabled')) {
            return response()->json($this->buildPayload());
        }

        $payload = Cache::remember(
            CatalogCacheKeys::siteSettings(),
            (int) config('catalog_performance.site_settings_ttl'),
            fn (): array => $this->buildPayload(),
        );

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $s = SiteSetting::current();

        return [
            'logo_text' => $s->logo_text,
            'logo_image_url' => $s->logo_image_url,
            'phone_display' => $s->phone_display,
            'phone_tel' => $s->phone_tel,
            'work_hours_short' => $s->work_hours_short,
            'support_email' => $s->support_email,
            'footer_legal_line' => $s->footer_legal_line,
            'feature_flags' => $s->mergedFeatureFlags(),
            'theme' => $s->mergedTheme(),
        ];
    }
}
