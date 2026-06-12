<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'code',
        'city',
        'region',
        'address',
        'priority',
        'is_active',
        'is_default',
        'external_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority')->orderBy('name');
    }
}
