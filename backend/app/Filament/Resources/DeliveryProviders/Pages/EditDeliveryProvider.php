<?php

namespace App\Filament\Resources\DeliveryProviders\Pages;

use App\Filament\Resources\DeliveryProviders\DeliveryProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryProvider extends EditRecord
{
    protected static string $resource = DeliveryProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
