<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Support\Store\StoreFeatureFlags;
use App\Support\Store\StoreTheme;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteSetting extends CreateRecord
{
    protected static string $resource = SiteSettingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['feature_flags'] = StoreFeatureFlags::merge($data['feature_flags'] ?? null);
        $data['theme'] = StoreTheme::merge($data['theme'] ?? null);

        return $data;
    }
}
