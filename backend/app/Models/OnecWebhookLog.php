<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnecWebhookLog extends Model
{
    protected $table = 'onec_webhook_log';

    protected $fillable = [
        'event',
        'order_id',
        'status',
        'attempts',
        'payload',
        'response',
        'next_retry_at',
    ];

    protected $casts = [
        'next_retry_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
