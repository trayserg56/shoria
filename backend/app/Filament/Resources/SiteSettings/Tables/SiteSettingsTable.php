<?php

namespace App\Filament\Resources\SiteSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class SiteSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('logo_text')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('logo_image_path')
                    ->label('Лого')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Изображение' : 'Текст'),
                Tables\Columns\TextColumn::make('phone_display')
                    ->label('Телефон'),
                Tables\Columns\TextColumn::make('work_hours_short')
                    ->label('График')
                    ->limit(40),
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
