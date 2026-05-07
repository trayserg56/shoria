<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GiftCertificate extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPLETED = 'depleted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'owner_user_id',
        'purchased_order_id',
        'code',
        'initial_amount',
        'balance_remaining',
        'currency',
        'status',
        'expires_at',
        'recipient_email',
        'admin_note',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function purchasedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'purchased_order_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCertificateRedemption::class);
    }

    /**
     * Ограничение выборки теми сертификатами, которые клиент может применить (логика как у {@see isUsable()}).
     * Для GET /api/me/gift-certificates используется по умолчанию; полный список — с query include_used=1.
     */
    public function scopeUsableForCustomer(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('balance_remaining', '>', 0)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ((float) $this->balance_remaining <= 0) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
