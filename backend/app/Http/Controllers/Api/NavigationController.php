<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavigationMenuItem;
use App\Support\Catalog\CatalogCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NavigationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! config('catalog_performance.cache_enabled')) {
            return response()->json($this->buildNavigationPayload());
        }

        $payload = Cache::remember(
            CatalogCacheKeys::navigation(),
            (int) config('catalog_performance.navigation_ttl'),
            fn (): array => $this->buildNavigationPayload(),
        );

        return response()->json($payload);
    }

    /**
     * @return array{header: list<array<string, mixed>>, footer: array{customers: list<array<string, mixed>>, account: list<array<string, mixed>>}}
     */
    private function buildNavigationPayload(): array
    {
        $items = NavigationMenuItem::query()
            ->where('is_active', true)
            ->orderBy('location')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $normalize = static fn (NavigationMenuItem $item): array => [
            'id' => $item->id,
            'label' => $item->label,
            'path' => $item->path,
            'is_external' => $item->is_external,
            'open_in_new_tab' => $item->open_in_new_tab,
        ];

        return [
            'header' => $items
                ->where('location', NavigationMenuItem::LOCATION_HEADER)
                ->values()
                ->map($normalize)
                ->all(),
            'footer' => [
                'customers' => $items
                    ->where('location', NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS)
                    ->values()
                    ->map($normalize)
                    ->all(),
                'account' => $items
                    ->where('location', NavigationMenuItem::LOCATION_FOOTER_ACCOUNT)
                    ->values()
                    ->map($normalize)
                    ->all(),
            ],
        ];
    }
}
