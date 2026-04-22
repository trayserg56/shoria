<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PromoCodeForm
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
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('discount_type')
                    ->label('Тип скидки')
                    ->options([
                        'fixed_percent' => 'Процент',
                        'fixed_amount' => 'Фиксированная сумма',
                    ])
                    ->required()
                    ->default('fixed_percent'),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Значение скидки')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\TextInput::make('min_subtotal')
                    ->label('Минимальная сумма заказа')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('usage_limit')
                    ->label('Лимит использований')
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('used_count')
                    ->label('Уже использован')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Действует с'),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Действует до'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ])
            ->columns(2);
    }
}
