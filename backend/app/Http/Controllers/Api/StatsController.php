<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember('shoria:stats:public', now()->addHour(), function (): array {
            $productsCount = Product::where('is_active', true)->count();
            $brandsCount   = Brand::where('is_active', true)->count();
            $ordersCount   = Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->count();

            // Средний рейтинг по всем активным отзывам
            $reviewsAvg = ProductReview::where('is_active', true)->avg('rating');

            return [
                'products_count' => $productsCount,
                'brands_count'   => $brandsCount,
                'orders_count'   => $ordersCount,
                'rating_avg'     => $reviewsAvg ? round((float) $reviewsAvg, 1) : null,
            ];
        });

        return response()->json($stats);
    }
}
