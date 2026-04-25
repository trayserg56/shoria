<?php

namespace App\Filament\Resources\LoyaltyProgramSettings\Pages;

use App\Filament\Resources\LoyaltyProgramSettings\LoyaltyProgramSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyProgramSettings extends ListRecords
{
    protected static string $resource = LoyaltyProgramSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
