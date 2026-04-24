<?php

namespace App\Filament\Resources\PaymentProviders\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PaymentProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Платежный провайдер')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('checkout_label')
                                    ->label('Название на checkout')
                                    ->maxLength(160),
                                Forms\Components\TextInput::make('code')
                                    ->label('Код')
                                    ->required()
                                    ->maxLength(32)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('driver')
                                    ->label('Драйвер')
                                    ->options([
                                        'tbank' => 'T-Bank',
                                        'sber' => 'Sber',
                                        'manual_cash' => 'Наличные / ручная оплата',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('mode')
                                    ->label('Режим')
                                    ->options([
                                        'sandbox' => 'Тестовый',
                                        'live' => 'Боевой',
                                    ])
                                    ->default('sandbox')
                                    ->required(),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_default')
                                    ->label('По умолчанию')
                                    ->default(false),
                            ])
                            ->columns(2),
                        Tab::make('Доступы')
                            ->schema([
                                Forms\Components\Placeholder::make('credentials_note')
                                    ->label('Подсказка')
                                    ->content('Сюда можно сохранить terminal key, merchant login, секреты, callback URL и любые служебные параметры. Значения шифруются в базе.'),
                                Forms\Components\KeyValue::make('config')
                                    ->label('Конфигурация')
                                    ->keyLabel('Ключ')
                                    ->valueLabel('Значение')
                                    ->addActionLabel('Добавить параметр')
                                    ->reorderable(),
                            ]),
                        Tab::make('Служебное')
                            ->schema([
                                Forms\Components\Placeholder::make('created_by_info')
                                    ->label('Создал')
                                    ->content(fn ($record): string => $record?->createdBy?->email ?? '—'),
                                Forms\Components\Placeholder::make('updated_by_info')
                                    ->label('Изменил')
                                    ->content(fn ($record): string => $record?->updatedBy?->email ?? '—'),
                                Forms\Components\Placeholder::make('created_at_info')
                                    ->label('Дата создания')
                                    ->content(fn ($record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('updated_at_info')
                                    ->label('Дата изменения')
                                    ->content(fn ($record): string => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
                            ])
                            ->columns(2)
                            ->visible(fn ($record): bool => $record !== null),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
