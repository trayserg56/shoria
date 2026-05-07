<?php

namespace App\Filament\Resources\GiftCertificates\Pages;

use App\Filament\Resources\GiftCertificates\GiftCertificateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGiftCertificate extends EditRecord
{
    protected static string $resource = GiftCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
