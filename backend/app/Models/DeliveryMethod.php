<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMethod extends Model
{
    use HasAuthorship, HasFactory;

    protected $fillable = [
        'code',
        'provider_code',
        'external_code',
        'name',
        'method_type',
        'fee',
        'calculation_mode',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_code', 'code');
    }
}
