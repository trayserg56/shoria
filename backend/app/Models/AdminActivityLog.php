<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Relation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'method',
    'path',
    'route_name',
    'entity_type',
    'entity_id',
    'ip',
    'user_agent',
    'response_status',
    'request_payload',
])]
class AdminActivityLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
        ];
    }

    #[Relation]
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function method(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => mb_strtoupper($value),
        );
    }
}
