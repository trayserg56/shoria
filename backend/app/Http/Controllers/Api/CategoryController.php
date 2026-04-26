<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $allCategories = Category::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get([
                'id',
                'parent_id',
                'name',
                'slug',
                'description',
                'image_url',
                'seo_title',
                'seo_description',
                'is_featured',
                'is_active',
            ]);

        $productCategoryIds = Product::query()
            ->where('is_active', true)
            ->select('category_id')
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->merge(
                DB::table('category_product')
                    ->join('products', 'products.id', '=', 'category_product.product_id')
                    ->where('products.is_active', true)
                    ->pluck('category_product.category_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $productCategoryIdSet = array_fill_keys($productCategoryIds->all(), true);

        $byParent = $allCategories
            ->groupBy(fn (Category $category): int => $category->parent_id ?? 0);

        $mapNode = function (Category $category) use (&$mapNode, $byParent, $productCategoryIdSet): ?array {
            $children = $byParent->get((int) $category->id, collect())
                ->map(fn (Category $child): ?array => $mapNode($child))
                ->filter()
                ->values()
                ->all();

            $hasOwnProducts = isset($productCategoryIdSet[(int) $category->id]);

            if (! $hasOwnProducts && $children === []) {
                return null;
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'is_featured' => $category->is_featured,
                'is_active' => $category->is_active,
                'seo_title' => $category->seo_title,
                'seo_description' => $category->seo_description,
                'parent_id' => $category->parent_id,
                'subcategories' => $children,
            ];
        };

        $categories = $byParent
            ->get(0, collect())
            ->map(fn (Category $category): ?array => $mapNode($category))
            ->filter()
            ->values();

        return response()->json($categories);
    }
}
