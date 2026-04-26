<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $variantSlug = trim((string) $request->query('variant_slug', ''));
        $variant = null;

        if ($variantSlug !== '') {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->where('slug', $variantSlug)
                ->first();
        }

        $reviewsQuery = ProductReview::query()
            ->with([
                'user:id,name',
                'orderItem:id,product_id,product_variant_id,product_name,variant_label',
                'orderItem.variant:id,slug,size_label,color_label',
            ])
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderByDesc('created_at');

        if ($variant) {
            $reviewsQuery->whereHas('orderItem', function ($query) use ($variant): void {
                $query->where('product_variant_id', $variant->id);
            });
        }

        $reviews = $reviewsQuery
            ->paginate(10)
            ->through(fn (ProductReview $review): array => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'review_text' => $review->review_text,
                'author_name' => $review->user?->name ?? 'Покупатель',
                'target' => [
                    'product_name' => $review->orderItem?->product_name ?: $product->name,
                    'variant_label' => $review->orderItem?->variant_label,
                    'variant_slug' => $review->orderItem?->variant?->slug,
                ],
                'is_verified_purchase' => (bool) $review->is_verified_purchase,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ]);

        $summary = $this->summaryForProduct($product->id);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'scope' => $variant ? 'variant' : 'all',
                'variant' => $variant ? [
                    'slug' => $variant->slug,
                    'size_label' => $variant->size_label,
                    'color_label' => $variant->color_label,
                ] : null,
            ],
            'summary' => $summary,
        ]);
    }

    public function upsert(Request $request, string $slug): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'message' => 'Требуется авторизация.',
            ], 401);
        }

        $product = Product::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['required', 'string', 'min:10', 'max:2000'],
            'variant_slug' => ['nullable', 'string'],
        ], [
            'rating.required' => 'Поставьте оценку.',
            'review_text.required' => 'Добавьте текст отзыва.',
            'review_text.min' => 'Отзыв должен содержать минимум 10 символов.',
        ]);

        $variantSlug = trim((string) ($validated['variant_slug'] ?? ''));
        $variant = null;

        if ($variantSlug !== '') {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->where('slug', $variantSlug)
                ->first();
        }

        $latestOrderItem = ProductReview::latestPurchasedOrderItemForUser(
            $user->id,
            $product->id,
            $variant?->id,
        );

        if (! $latestOrderItem && $variant) {
            $latestOrderItem = ProductReview::latestPurchasedOrderItemForUser($user->id, $product->id);
        }

        if (! $latestOrderItem) {
            return response()->json([
                'message' => 'Оставить отзыв можно только после покупки этого товара.',
            ], 403);
        }

        $review = ProductReview::query()->firstOrNew([
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);

        $isNew = ! $review->exists;

        $review->fill([
            'order_item_id' => $latestOrderItem->id,
            'rating' => (int) $validated['rating'],
            'review_text' => trim($validated['review_text']),
            'is_active' => true,
            'is_verified_purchase' => true,
        ]);
        $review->save();
        $review->load([
            'user:id,name',
            'orderItem:id,product_id,product_variant_id,product_name,variant_label',
            'orderItem.variant:id,slug,size_label,color_label',
        ]);

        return response()->json([
            'review' => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'review_text' => $review->review_text,
                'author_name' => $review->user?->name ?? 'Покупатель',
                'target' => [
                    'product_name' => $review->orderItem?->product_name ?: $product->name,
                    'variant_label' => $review->orderItem?->variant_label,
                    'variant_slug' => $review->orderItem?->variant?->slug,
                ],
                'is_verified_purchase' => (bool) $review->is_verified_purchase,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ],
            'summary' => $this->summaryForProduct($product->id),
            'is_new' => $isNew,
        ], $isNew ? 201 : 200);
    }

    public function my(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'message' => 'Требуется авторизация.',
            ], 401);
        }

        $reviews = ProductReview::query()
            ->with([
                'product:id,name,slug,category_id',
                'product.category:id,name,slug',
                'orderItem:id,product_id,product_variant_id,product_name,variant_label',
                'orderItem.variant:id,slug,size_label,color_label',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (ProductReview $review): array => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'review_text' => $review->review_text,
                'is_verified_purchase' => (bool) $review->is_verified_purchase,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
                'target' => [
                    'product_name' => $review->orderItem?->product_name ?: $review->product?->name,
                    'variant_label' => $review->orderItem?->variant_label,
                    'variant_slug' => $review->orderItem?->variant?->slug,
                ],
                'product' => [
                    'id' => $review->product?->id,
                    'name' => $review->product?->name,
                    'slug' => $review->product?->slug,
                    'category' => $review->product?->category ? [
                        'name' => $review->product->category->name,
                        'slug' => $review->product->category->slug,
                    ] : null,
                ],
            ])
            ->values();

        $reviewedProductIds = $reviews
            ->pluck('product.id')
            ->filter()
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        $eligibleOrderItems = OrderItem::query()
            ->with([
                'order:id,order_number,placed_at',
                'product:id,name,slug,category_id',
                'product.category:id,name,slug',
            ])
            ->whereNotNull('product_id')
            ->whereHas('order', function ($query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['paid', 'processing', 'completed']);
            })
            ->latest('id')
            ->get()
            ->unique('product_id')
            ->reject(fn (OrderItem $item): bool => $reviewedProductIds->contains((int) $item->product_id))
            ->values();

        return response()->json([
            'reviews' => $reviews,
            'eligible_products' => $eligibleOrderItems->map(fn (OrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product?->name ?? $item->product_name,
                'product_slug' => $item->product?->slug ?? $item->product_slug,
                'category' => $item->product?->category ? [
                    'name' => $item->product->category->name,
                    'slug' => $item->product->category->slug,
                ] : null,
                'order_number' => $item->order?->order_number,
                'purchased_at' => $item->order?->placed_at,
            ])->values(),
        ]);
    }

    /**
     * @return array{count: int, average: float|null}
     */
    private function summaryForProduct(int $productId): array
    {
        $query = ProductReview::query()
            ->where('product_id', $productId)
            ->where('is_active', true);

        $count = (int) $query->count();
        $average = $count > 0 ? round((float) $query->avg('rating'), 1) : null;

        return [
            'count' => $count,
            'average' => $average,
        ];
    }
}
