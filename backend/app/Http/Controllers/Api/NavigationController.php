<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavigationMenuItem;
use Illuminate\Http\JsonResponse;

class NavigationController extends Controller
{
    public function __invoke(): JsonResponse
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

        return response()->json([
            'header' => $items
                ->where('location', NavigationMenuItem::LOCATION_HEADER)
                ->values()
                ->map($normalize),
            'footer' => [
                'customers' => $items
                    ->where('location', NavigationMenuItem::LOCATION_FOOTER_CUSTOMERS)
                    ->values()
                    ->map($normalize),
                'account' => $items
                    ->where('location', NavigationMenuItem::LOCATION_FOOTER_ACCOUNT)
                    ->values()
                    ->map($normalize),
            ],
        ]);
    }
}
