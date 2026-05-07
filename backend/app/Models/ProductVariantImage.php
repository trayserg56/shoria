<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantImage extends Model
{
    use HasAuthorship, HasFactory, InvalidatesCatalogCache;

    protected $fillable = [
        'product_variant_id',
        'url',
        'alt',
        'is_cover',
        'sort_order',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
        );
    }
}
