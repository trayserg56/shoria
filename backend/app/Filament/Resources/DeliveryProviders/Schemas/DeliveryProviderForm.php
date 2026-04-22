<?php

namespace App\Filament\Resources\DeliveryProviders\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DeliveryProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Провайдер доставки')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('code')
                                    ->label('Код')
                                    ->required()
                                    ->maxLength(32)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('driver')
                                    ->label('Драйвер')
                                    ->options([
                                        'manual' => 'Локальная доставка',
                                        'cdek' => 'CDEK',
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
                                Forms\Components\Toggle::make('supports_pickup_points')
                                    ->label('Поддерживает ПВЗ')
                                    ->default(false),
                                Forms\Components\Toggle::make('supports_tracking')
                                    ->label('Поддерживает трекинг')
                                    ->default(false),
                            ])
                            ->columns(2),
                        Tab::make('Доступы')
                            ->schema([
                                Forms\Components\Placeholder::make('delivery_credentials_note')
                                    ->label('Подсказка')
                                    ->content('Сюда можно сохранить токены, account id, client secret, warehouse code и другие параметры. Значения шифруются в базе.'),
                                Forms\Components\KeyValue::make('config')
                                    ->label('Конфигурация')
                                    ->keyLabel('Ключ')
                                    ->valueLabel('Значение')
                                    ->addActionLabel('Добавить параметр')
                                    ->reorderable(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
