<?php

namespace App\Filament\Resources\PromoCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state) => $state === 'fixed_percent' ? 'Процент' : 'Фиксированная'),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Скидка')
                    ->formatStateUsing(fn ($state, $record) => $record->discount_type === 'fixed_percent'
                        ? ((float) $state) . '%'
                        : number_format((float) $state, 0, '.', ' ') . ' ₽'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Использован')
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('Лимит')
                    ->placeholder('∞'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('До')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Без срока')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
