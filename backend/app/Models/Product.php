<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'brand',
        'slug',
        'sku',
        'description',
        'seo_title',
        'seo_description',
        'price',
        'old_price',
        'currency',
        'is_featured',
        'is_hit',
        'is_new',
        'is_customer_choice',
        'is_active',
        'stock',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_hit' => 'boolean',
        'is_new' => 'boolean',
        'is_customer_choice' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }

    public function brandEntity(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function newsPosts(): BelongsToMany
    {
        return $this->belongsToMany(NewsPost::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if (! $product->brand_id) {
                return;
            }

            $brandName = $product->brandEntity?->name
                ?? Brand::query()->whereKey($product->brand_id)->value('name');

            if (is_string($brandName) && trim($brandName) !== '') {
                $product->brand = $brandName;
            }
        });
    }
}
