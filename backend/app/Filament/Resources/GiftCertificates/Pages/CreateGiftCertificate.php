<?php

namespace App\Filament\Resources\GiftCertificates\Pages;

use App\Filament\Resources\GiftCertificates\GiftCertificateResource;
use App\Models\GiftCertificate;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCertificate extends CreateRecord
{
    protected static string $resource = GiftCertificateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['code'])) {
            $data['code'] = GiftCertificate::generateUniqueCode();
        } else {
            $data['code'] = strtoupper(preg_replace('/\s+/', '', trim((string) $data['code'])));
        }

        $data['balance_remaining'] = $data['initial_amount'];
        $data['status'] = $data['status'] ?? GiftCertificate::STATUS_ACTIVE;

        return $data;
    }
}
