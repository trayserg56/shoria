<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    use HasAuthorship;

    protected $fillable = [
        'logo_text',
        'logo_image_path',
        'phone_display',
        'phone_tel',
        'work_hours_short',
    ];

    public function getLogoImageUrlAttribute(): ?string
    {
        if (empty($this->logo_image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_image_path);
    }

    public static function current(): self
    {
        /** @var self $row */
        $row = static::query()->firstOrCreate(
            ['id' => 1],
            [
                'logo_text' => 'Shoria',
                'logo_image_path' => null,
                'phone_display' => '+7 (900) 000-00-00',
                'phone_tel' => '+79000000000',
                'work_hours_short' => 'Пн–Вс: 10:00–20:00',
            ],
        );

        return $row;
    }
}
