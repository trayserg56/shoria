<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Warehouse;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Остатки по складам';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('warehouse_id')
                    ->label('Склад')
                    ->options(Warehouse::active()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('qty')
                    ->label('Количество')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('reserved_qty')
                    ->label('Зарезервировано')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.city')
                    ->label('Город')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Кол-во')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('reserved_qty')
                    ->label('Резерв')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('available_qty')
                    ->label('Доступно')
                    ->getStateUsing(fn ($record) => max(0, $record->qty - $record->reserved_qty))
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Добавить склад'),
            ])
            ->recordActions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('warehouse.name');
    }
}
