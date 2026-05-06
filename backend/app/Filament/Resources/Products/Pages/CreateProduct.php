<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductCharacteristics;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['characteristics']) && is_array($data['characteristics'])) {
            $data['characteristics'] = ProductCharacteristics::expandForStorage($data['characteristics']);
        }

        return $data;
    }
}
