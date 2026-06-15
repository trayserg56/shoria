<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NewsPost extends Model
{
    use HasAuthorship, HasFactory, InvalidatesCatalogCache;

    protected $fillable = [
        'title',
        'slug',
        'content_type',
        'excerpt',
        'content',
        'blocks',
        'cover_url',
        'seo_title',
        'seo_description',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'blocks' => 'array',
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
        );
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
