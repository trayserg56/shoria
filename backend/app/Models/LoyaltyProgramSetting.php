<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyProgramSetting extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'is_enabled',
        'base_accrual_percent',
        'max_redeem_percent',
        'point_value',
        'min_order_total_for_redeem',
        'tiers',
        'terms_content',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'base_accrual_percent' => 'decimal:2',
        'max_redeem_percent' => 'decimal:2',
        'point_value' => 'decimal:2',
        'min_order_total_for_redeem' => 'decimal:2',
        'tiers' => 'array',
    ];

    public static function current(): self
    {
        /** @var self $setting */
        $setting = static::query()->firstOrCreate(
            ['id' => 1],
            [
                'is_enabled' => false,
                'base_accrual_percent' => 5,
                'max_redeem_percent' => 25,
                'point_value' => 1,
                'min_order_total_for_redeem' => 0,
                'tiers' => [],
                'terms_content' => null,
            ],
        );

        return $setting;
    }
}
