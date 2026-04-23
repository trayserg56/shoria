<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $brand): void {
            $slugSource = trim((string) ($brand->slug ?: $brand->name));
            $baseSlug = Str::slug($slugSource) ?: 'brand';
            $slug = $baseSlug;
            $suffix = 2;

            while (static::query()
                ->where('slug', $slug)
                ->when($brand->exists, fn ($query) => $query->where('id', '!=', $brand->id))
                ->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $brand->slug = $slug;
        });
    }
}
