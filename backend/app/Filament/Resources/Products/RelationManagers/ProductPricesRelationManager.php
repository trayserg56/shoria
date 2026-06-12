<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\PriceType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'productPrices';

    protected static ?string $title = 'Цены по типам';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('price_type_id')
                    ->label('Тип цены')
                    ->options(PriceType::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('price')
                    ->label('Цена')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\Select::make('currency')
                    ->label('Валюта')
                    ->options(['RUB' => 'RUB (₽)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)'])
                    ->default('RUB')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priceType.name')
                    ->label('Тип цены')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Валюта')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить цену'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('price_type_id');
    }
}
