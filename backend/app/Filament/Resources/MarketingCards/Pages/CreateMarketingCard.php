<?php

namespace App\Filament\Resources\MarketingCards\Pages;

use App\Filament\Resources\MarketingCards\MarketingCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingCard extends CreateRecord
{
    protected static string $resource = MarketingCardResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeLinesText($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLinesText(array $data): array
    {
        $raw = $data['lines_text'] ?? '';
        $data['lines'] = collect(preg_split('/\r\n|\r|\n/', (string) $raw))
            ->map(fn (string $s): string => trim($s))
            ->filter()
            ->values()
            ->all();
        unset($data['lines_text']);

        return $data;
    }
}
