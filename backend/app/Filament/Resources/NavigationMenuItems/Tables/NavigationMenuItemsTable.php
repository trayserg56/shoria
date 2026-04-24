<?php

namespace App\Filament\Resources\NavigationMenuItems\Tables;

use App\Models\NavigationMenuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationMenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location')
                    ->label('Область')
                    ->formatStateUsing(
                        fn (string $state): string => NavigationMenuItem::locationOptions()[$state] ?? $state,
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Пункт')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Ссылка')
                    ->searchable()
                    ->copyable()
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_external')
                    ->label('Внешн.')
                    ->boolean(),
                Tables\Columns\IconColumn::make('open_in_new_tab')
                    ->label('Новая вкладка')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('createdBy.email')
                    ->label('Создал')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('updatedBy.email')
                    ->label('Изменил')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->label('Область')
                    ->options(NavigationMenuItem::locationOptions()),
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
            ->defaultSort('sort_order');
    }
}
