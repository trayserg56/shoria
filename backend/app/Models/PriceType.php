<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_default',
        'external_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public static function retail(): ?self
    {
        return static::where('code', 'retail')->first();
    }
}
