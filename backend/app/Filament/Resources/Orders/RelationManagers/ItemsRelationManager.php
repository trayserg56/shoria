<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Состав заказа';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(''),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Товар')
                    ->searchable(),
                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Вариант')
                    ->placeholder('Базовый'),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Кол-во'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->order->currency),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Сумма')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->order->currency),
            ])
            ->defaultSort('id');
    }
}
