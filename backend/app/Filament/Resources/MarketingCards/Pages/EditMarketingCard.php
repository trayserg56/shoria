<?php

namespace App\Filament\Resources\MarketingCards\Pages;

use App\Filament\Resources\MarketingCards\MarketingCardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingCard extends EditRecord
{
    protected static string $resource = MarketingCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $lines = $data['lines'] ?? [];
        if (is_array($lines)) {
            $data['lines_text'] = implode("\n", array_map(
                fn ($s): string => is_string($s) ? $s : (string) $s,
                $lines,
            ));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('lines_text', $data)) {
            $raw = $data['lines_text'] ?? '';
            $data['lines'] = collect(preg_split('/\r\n|\r|\n/', (string) $raw))
                ->map(fn (string $s): string => trim($s))
                ->filter()
                ->values()
                ->all();
            unset($data['lines_text']);
        }

        return $data;
    }
}
