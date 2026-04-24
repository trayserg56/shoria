<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_featured')
                    ->orderBy('sort_order'),
            ])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'image_url', 'seo_title', 'seo_description', 'is_featured', 'is_active'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'is_featured' => $category->is_featured,
                'is_active' => $category->is_active,
                'seo_title' => $category->seo_title,
                'seo_description' => $category->seo_description,
                'subcategories' => $category->children->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'description' => $child->description,
                    'image_url' => $child->image_url,
                    'is_featured' => $child->is_featured,
                    'is_active' => $child->is_active,
                    'seo_title' => $child->seo_title,
                    'seo_description' => $child->seo_description,
                    'parent_id' => $child->parent_id,
                ])->values(),
            ])->values();

        return response()->json($categories);
    }
}
