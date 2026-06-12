<?php

namespace App\Filament\Resources\OnecExchangeSessions;

use App\Filament\Resources\OnecExchangeSessions\Pages\ListOnecExchangeSessions;
use App\Models\OnecExchangeSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OnecExchangeSessionResource extends Resource
{
    protected static ?string $model = OnecExchangeSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Журнал обмена 1С';

    protected static string|UnitEnum|null $navigationGroup = 'Склад и цены';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('direction')->label('Направление')
                    ->badge()
                    ->color(fn (string $state) => $state === 'import' ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state) => $state === 'import' ? 'Импорт' : 'Экспорт'),
                TextColumn::make('type')->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'products' => 'Товары',
                        'prices' => 'Цены и остатки',
                        'orders' => 'Заказы',
                        default => $state,
                    }),
                TextColumn::make('status')->label('Статус')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'error' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'success' => 'Успешно',
                        'error' => 'Ошибка',
                        'started' => 'Запущен',
                        default => $state,
                    }),
                TextColumn::make('records_processed')->label('Записей'),
                TextColumn::make('records_created')->label('Создано'),
                TextColumn::make('records_updated')->label('Обновлено'),
                TextColumn::make('records_skipped')->label('Пропущено'),
                TextColumn::make('error_message')->label('Ошибка')->limit(60)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')->label('Начало')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('finished_at')->label('Завершение')->dateTime('d.m.Y H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Направление')
                    ->options(['import' => 'Импорт', 'export' => 'Экспорт']),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(['started' => 'Запущен', 'success' => 'Успешно', 'error' => 'Ошибка']),
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options(['products' => 'Товары', 'prices' => 'Цены и остатки', 'orders' => 'Заказы']),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnecExchangeSessions::route('/'),
        ];
    }
}
