<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuthorship
{
    protected static function bootHasAuthorship(): void
    {
        static::creating(function ($model): void {
            $authId = auth()->id();
            if (! is_numeric($authId)) {
                return;
            }
            $userId = (int) $authId;

            if (! $model->getAttribute('created_by')) {
                $model->setAttribute('created_by', $userId);
            }

            $model->setAttribute('updated_by', $userId);
        });

        static::updating(function ($model): void {
            $authId = auth()->id();
            if (! is_numeric($authId)) {
                return;
            }
            $userId = (int) $authId;

            $model->setAttribute('updated_by', $userId);
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
