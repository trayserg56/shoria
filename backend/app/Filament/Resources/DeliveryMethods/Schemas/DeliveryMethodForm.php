<?php

namespace App\Filament\Resources\DeliveryMethods\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class DeliveryMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Forms\Components\TextInput::make('provider_code')
                    ->label('Код провайдера')
                    ->maxLength(32),
                Forms\Components\TextInput::make('external_code')
                    ->label('Внешний код')
                    ->maxLength(64),
                Forms\Components\Select::make('method_type')
                    ->label('Тип метода')
                    ->options([
                        'courier' => 'Курьер',
                        'pickup' => 'Самовывоз / ПВЗ',
                    ])
                    ->default('courier')
                    ->required(),
                Forms\Components\TextInput::make('fee')
                    ->label('Стоимость')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('calculation_mode')
                    ->label('Расчет стоимости')
                    ->options([
                        'flat' => 'Фиксированная',
                        'provider' => 'Через провайдера',
                    ])
                    ->default('flat')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ])
            ->columns(2);
    }
}
