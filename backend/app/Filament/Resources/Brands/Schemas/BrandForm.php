<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(140)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Порядок')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
                \Filament\Schemas\Components\Section::make('Служебное')
                    ->schema([
                        Forms\Components\Placeholder::make('created_by_info')
                            ->label('Создал')
                            ->content(fn ($record): string => $record?->createdBy?->email ?? '—'),
                        Forms\Components\Placeholder::make('updated_by_info')
                            ->label('Изменил')
                            ->content(fn ($record): string => $record?->updatedBy?->email ?? '—'),
                        Forms\Components\Placeholder::make('created_at_info')
                            ->label('Дата создания')
                            ->content(fn ($record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                        Forms\Components\Placeholder::make('updated_at_info')
                            ->label('Дата изменения')
                            ->content(fn ($record): string => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => $record !== null),
            ])
            ->columns(2);
    }
}
