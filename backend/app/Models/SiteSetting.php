<?php

namespace App\Models;

use App\Models\Concerns\HasAuthorship;
use App\Models\Concerns\InvalidatesCatalogCache;
use App\Support\Store\StoreFeedSettings;
use App\Support\Store\StoreFeatureFlags;
use App\Support\Store\StoreTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    use HasAuthorship, InvalidatesCatalogCache;

    protected $fillable = [
        'logo_text',
        'logo_image_path',
        'phone_display',
        'phone_tel',
        'work_hours_short',
        'support_email',
        'footer_legal_line',
        'feature_flags',
        'theme',
        'feed_settings',
    ];

    protected $casts = [
        'feature_flags' => 'array',
        'theme' => 'array',
        'feed_settings' => 'array',
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

    /**
     * @return array<string, bool>
     */
    public function mergedFeatureFlags(): array
    {
        return StoreFeatureFlags::merge($this->feature_flags);
    }

    public function isFeatureEnabled(string $key): bool
    {
        $flags = $this->mergedFeatureFlags();

        return ($flags[$key] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedTheme(): array
    {
        return StoreTheme::merge($this->theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedFeedSettings(): array
    {
        return StoreFeedSettings::merge($this->feed_settings);
    }
}
