<?php

namespace App\Filament\Pages;

use App\Support\Admin\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class YandexFeedPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?string $navigationLabel = 'YML-фид Яндекс.Маркет';

    protected static ?string $title = 'YML-фид для Яндекс.Маркет';

    protected static string|UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'yandex-feed';

    public function getView(): string
    {
        return 'filament.pages.yandex-feed';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::canManageContentResource('yandex_feed');
    }

    protected function getHeaderActions(): array
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $feedUrl = $appUrl.'/feed/yandex.xml';

        return [
            Action::make('open_feed')
                ->label('Открыть фид')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url($feedUrl, shouldOpenInNewTab: true),

            Action::make('refresh_feed')
                ->label('Обновить фид')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Сбросить кэш фида?')
                ->modalDescription('Фид будет перестроен из БД при следующем запросе.')
                ->action(function () {
                    Cache::forget('shoria:feed:yandex:yml');
                    Notification::make()
                        ->title('Кэш фида сброшен')
                        ->success()
                        ->send();
                }),
        ];
    }
}
