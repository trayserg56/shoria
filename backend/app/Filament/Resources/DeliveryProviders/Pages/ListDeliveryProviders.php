<?php

namespace App\Filament\Resources\DeliveryProviders\Pages;

use App\Filament\Resources\DeliveryProviders\DeliveryProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryProviders extends ListRecords
{
    protected static string $resource = DeliveryProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
