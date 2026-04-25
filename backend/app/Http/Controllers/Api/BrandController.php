<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::query()
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
            ]);

        return response()->json($brands);
    }
}
