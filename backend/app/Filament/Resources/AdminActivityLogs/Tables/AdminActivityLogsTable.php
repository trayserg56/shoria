<?php

namespace App\Filament\Resources\AdminActivityLogs\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class AdminActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Кто')
                    ->searchable()
                    ->placeholder('system'),
                Tables\Columns\TextColumn::make('method')
                    ->label('Метод')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Сущность')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label('ID')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('Путь')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 500 ? 'danger' : ($state >= 400 ? 'warning' : 'success'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ]),
                Tables\Filters\Filter::make('errors_only')
                    ->label('Только ошибки')
                    ->query(fn ($query) => $query->where('response_status', '>=', 400)),
            ])
            ->recordActions([])
            ->bulkActions([]);
    }
}
