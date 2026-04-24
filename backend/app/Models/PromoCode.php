<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'applies_to',
        'items_match_mode',
        'included_product_ids',
        'included_category_ids',
        'included_brand_ids',
        'min_subtotal',
        'min_items_count',
        'max_discount_amount',
        'free_delivery',
        'first_order_only',
        'usage_limit',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'included_product_ids' => 'array',
        'included_category_ids' => 'array',
        'included_brand_ids' => 'array',
        'min_subtotal' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'free_delivery' => 'boolean',
        'first_order_only' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }
}
