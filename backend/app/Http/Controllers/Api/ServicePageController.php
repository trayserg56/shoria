<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicePage;
use Illuminate\Http\JsonResponse;

class ServicePageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = ServicePage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'title',
                'slug',
                'excerpt',
            ]);

        return response()->json($pages);
    }

    public function show(string $slug): JsonResponse
    {
        $page = ServicePage::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'excerpt' => $page->excerpt,
            'content' => $page->content,
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'updated_at' => optional($page->updated_at)?->toIso8601String(),
        ]);
    }
}

