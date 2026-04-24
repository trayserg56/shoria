<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'product_id',
        'slug',
        'size_label',
        'color_label',
        'sku',
        'price',
        'stock',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductVariantImage::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $variant): void {
            if ($variant->slug !== null && trim($variant->slug) !== '') {
                $variant->slug = Str::slug($variant->slug);

                return;
            }

            $parts = array_filter([
                $variant->color_label,
                $variant->size_label,
            ], static fn (?string $value): bool => $value !== null && trim($value) !== '');

            $base = Str::slug(implode('-', $parts)) ?: 'variant';
            $slug = $base;
            $suffix = 2;

            while (static::query()
                ->where('product_id', $variant->product_id)
                ->where('slug', $slug)
                ->when($variant->exists, fn ($query) => $query->where('id', '!=', $variant->id))
                ->exists()) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $variant->slug = $slug;
        });
    }
}
