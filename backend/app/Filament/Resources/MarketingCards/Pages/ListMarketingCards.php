<?php

namespace App\Filament\Resources\MarketingCards\Pages;

use App\Filament\Resources\MarketingCards\MarketingCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingCards extends ListRecords
{
    protected static string $resource = MarketingCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
