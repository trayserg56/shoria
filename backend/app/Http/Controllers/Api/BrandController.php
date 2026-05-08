<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\Catalog\CatalogCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        if (! config('catalog_performance.cache_enabled')) {
            return response()->json($this->buildBrandsPayload());
        }

        $payload = Cache::remember(
            CatalogCacheKeys::brandsList(),
            (int) config('catalog_performance.brands_ttl'),
            fn (): array => $this->buildBrandsPayload(),
        );

        return response()->json($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildBrandsPayload(): array
    {
        return Brand::query()
            ->where('is_active', true)
            ->withCount([
                'products as products_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Brand $brand): bool => (int) $brand->products_count > 0)
            ->values()
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'image_url' => $brand->image_url,
                'products_count' => (int) $brand->products_count,
            ])
            ->all();
    }
}
