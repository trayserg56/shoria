<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'review_text',
        'is_active',
        'is_verified_purchase',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
        'is_verified_purchase' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public static function latestPurchasedOrderItemForUser(
        int $userId,
        int $productId,
        ?int $productVariantId = null,
    ): ?OrderItem
    {
        $query = OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($userId): void {
                $query
                    ->where('user_id', $userId)
                    ->whereIn('status', ['paid', 'processing', 'completed']);
            });

        if ($productVariantId !== null) {
            $query->where('product_variant_id', $productVariantId);
        }

        return $query->latest('id')->first();
    }

    public static function canUserReviewProduct(
        int $userId,
        int $productId,
        ?int $productVariantId = null,
    ): bool
    {
        return self::latestPurchasedOrderItemForUser($userId, $productId, $productVariantId) !== null;
    }
}
