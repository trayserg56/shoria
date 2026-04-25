<?php

namespace App\Filament\Resources\LoyaltyProgramSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyProgramSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Включена')
                    ->boolean(),
                Tables\Columns\TextColumn::make('base_accrual_percent')
                    ->label('Базовое начисление')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('max_redeem_percent')
                    ->label('Макс. списание')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('point_value')
                    ->label('1 балл = ₽')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('updatedBy.email')
                    ->label('Изменил'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id');
    }
}
