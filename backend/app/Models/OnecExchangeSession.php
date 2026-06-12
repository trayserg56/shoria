<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnecExchangeSession extends Model
{
    protected $fillable = [
        'direction',
        'type',
        'status',
        'records_processed',
        'records_created',
        'records_updated',
        'records_skipped',
        'error_message',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function markSuccess(array $counts = []): void
    {
        $this->update(array_merge([
            'status' => 'success',
            'finished_at' => now(),
        ], $counts));
    }

    public function markError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
