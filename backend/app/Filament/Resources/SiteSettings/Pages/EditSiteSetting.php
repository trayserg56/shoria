<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Support\Store\StoreFeatureFlags;
use Filament\Resources\Pages\EditRecord;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['feature_flags'] = StoreFeatureFlags::merge($data['feature_flags'] ?? null);

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['feature_flags']) && is_array($data['feature_flags'])) {
            $data['feature_flags'] = StoreFeatureFlags::merge($data['feature_flags']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
