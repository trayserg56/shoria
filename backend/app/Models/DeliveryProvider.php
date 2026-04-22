<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'driver',
        'mode',
        'is_active',
        'is_default',
        'supports_pickup_points',
        'supports_tracking',
        'sort_order',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'supports_pickup_points' => 'boolean',
        'supports_tracking' => 'boolean',
        'config' => 'encrypted:array',
    ];
}
