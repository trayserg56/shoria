<?php

namespace App\Filament\Resources\GiftCertificates\Pages;

use App\Filament\Resources\GiftCertificates\GiftCertificateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGiftCertificates extends ListRecords
{
    protected static string $resource = GiftCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
