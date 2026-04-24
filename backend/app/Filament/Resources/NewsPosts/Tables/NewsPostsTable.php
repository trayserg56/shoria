<?php

namespace App\Filament\Resources\NewsPosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class NewsPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('content_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'guide' => 'Гайд',
                        'collection' => 'Подборка',
                        'promo' => 'Промо',
                        default => 'Новость',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'guide' => 'info',
                        'collection' => 'success',
                        'promo' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Активна')
                    ->boolean(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
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
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published'),
                Tables\Filters\SelectFilter::make('content_type')
                    ->label('Тип')
                    ->options([
                        'news' => 'Новость',
                        'guide' => 'Гайд',
                        'collection' => 'Подборка',
                        'promo' => 'Промо',
                    ]),
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
            ->defaultSort('published_at', 'desc');
    }
}
