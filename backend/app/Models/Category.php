<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasAuthorship, HasFactory, InvalidatesCatalogCache;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon_url',
        'seo_title',
        'seo_description',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
        );
    }

    protected function iconUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
        );
    }
}
