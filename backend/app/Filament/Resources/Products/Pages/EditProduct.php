<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductCharacteristics;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['characteristics']) && is_array($data['characteristics'])) {
            $data['characteristics'] = ProductCharacteristics::collapseForForm($data['characteristics']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['characteristics']) && is_array($data['characteristics'])) {
            $data['characteristics'] = ProductCharacteristics::expandForStorage($data['characteristics']);
        }

        return $data;
    }
}
