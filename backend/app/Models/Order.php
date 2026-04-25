<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'session_id',
        'status',
        'order_status',
        'payment_status',
        'fulfillment_status',
        'refund_status',
        'delivery_method',
        'payment_method',
        'currency',
        'subtotal',
        'discount_total',
        'delivery_total',
        'total',
        'customer_name',
        'customer_email',
        'customer_phone',
        'first_touch_source',
        'first_touch_medium',
        'first_touch_campaign',
        'first_touch_content',
        'first_touch_term',
        'first_touch_referrer_host',
        'first_touch_landing_path',
        'comment',
        'promo_code',
        'loyalty_points_spent',
        'loyalty_discount_total',
        'loyalty_points_earned',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'loyalty_discount_total' => 'decimal:2',
        'delivery_total' => 'decimal:2',
        'total' => 'decimal:2',
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $newStatusFields = ['order_status', 'payment_status', 'fulfillment_status', 'refund_status'];
            $hasDirtyNewStatusFields = collect($newStatusFields)->contains(
                fn (string $field) => $order->isDirty($field),
            );

            if ($order->isDirty('status') && ! $hasDirtyNewStatusFields) {
                $order->syncFromLegacyStatus();
            }

            $order->status = $order->resolveLegacyStatus();
            $order->syncLifecycleTimestamps();
        });

        static::created(function (Order $order): void {
            foreach (['order_status', 'payment_status', 'fulfillment_status', 'refund_status'] as $field) {
                OrderStatusLog::query()->create([
                    'order_id' => $order->id,
                    'field' => $field,
                    'old_value' => null,
                    'new_value' => (string) $order->getAttribute($field),
                    'source' => 'system',
                ]);
            }
        });

        static::updated(function (Order $order): void {
            foreach (['order_status', 'payment_status', 'fulfillment_status', 'refund_status'] as $field) {
                if (! $order->wasChanged($field)) {
                    continue;
                }

                OrderStatusLog::query()->create([
                    'order_id' => $order->id,
                    'field' => $field,
                    'old_value' => $order->getOriginal($field),
                    'new_value' => (string) $order->getAttribute($field),
                    'source' => 'system',
                ]);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest();
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    public function paymentWebhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class)->latest();
    }

    public function resolveLegacyStatus(): string
    {
        if ($this->order_status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->order_status === 'completed' || $this->fulfillment_status === 'delivered') {
            return 'completed';
        }

        if (in_array($this->fulfillment_status, ['processing', 'packed', 'shipped', 'ready_for_pickup', 'returned'], true)) {
            return 'processing';
        }

        if (in_array($this->payment_status, ['paid', 'authorized', 'partially_refunded', 'refunded'], true)) {
            return 'paid';
        }

        return 'new';
    }

    public function syncLifecycleTimestamps(): void
    {
        if ($this->order_status === 'confirmed' && ! $this->confirmed_at) {
            $this->confirmed_at = now();
        }

        if ($this->order_status === 'cancelled' && ! $this->cancelled_at) {
            $this->cancelled_at = now();
        }

        if ($this->order_status === 'completed' && ! $this->completed_at) {
            $this->completed_at = now();
        }
    }

    public function syncFromLegacyStatus(): void
    {
        match ($this->status) {
            'paid' => $this->fill([
                'order_status' => 'confirmed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'pending',
                'refund_status' => 'none',
            ]),
            'processing' => $this->fill([
                'order_status' => 'confirmed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'processing',
                'refund_status' => 'none',
            ]),
            'completed' => $this->fill([
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'delivered',
                'refund_status' => 'none',
            ]),
            'cancelled' => $this->fill([
                'order_status' => 'cancelled',
                'payment_status' => in_array($this->payment_status, ['paid', 'partially_refunded', 'refunded'], true)
                    ? $this->payment_status
                    : 'cancelled',
                'fulfillment_status' => 'pending',
                'refund_status' => $this->refund_status ?: 'none',
            ]),
            default => $this->fill([
                'order_status' => 'placed',
                'payment_status' => $this->payment_method === 'card' ? 'pending' : 'unpaid',
                'fulfillment_status' => 'pending',
                'refund_status' => 'none',
            ]),
        };
    }
}
