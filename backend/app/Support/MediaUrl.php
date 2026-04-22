<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUrl
{
    public static function resolve(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (
            Str::startsWith($value, ['http://', 'https://', 'data:', 'blob:']) ||
            Str::startsWith($value, '/storage/')
        ) {
            return $value;
        }

        if (Str::startsWith($value, 'storage/')) {
            return '/' . $value;
        }

        if (Str::startsWith($value, '/')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
