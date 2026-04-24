<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NavigationMenuItem extends Model
{
    use HasFactory;

    public const LOCATION_HEADER = 'header';

    public const LOCATION_FOOTER_CUSTOMERS = 'footer_customers';

    public const LOCATION_FOOTER_ACCOUNT = 'footer_account';

    protected $fillable = [
        'location',
        'label',
        'path',
        'is_external',
        'open_in_new_tab',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'open_in_new_tab' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function locationOptions(): array
    {
        return [
            self::LOCATION_HEADER => 'Шапка',
            self::LOCATION_FOOTER_CUSTOMERS => 'Футер · Покупателям',
            self::LOCATION_FOOTER_ACCOUNT => 'Футер · Аккаунт',
        ];
    }
}

