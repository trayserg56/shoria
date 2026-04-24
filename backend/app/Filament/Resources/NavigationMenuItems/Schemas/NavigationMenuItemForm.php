<?php

namespace App\Filament\Resources\NavigationMenuItems\Schemas;

use App\Models\NavigationMenuItem;
use Filament\Forms;
use Filament\Schemas\Schema;

class NavigationMenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('location')
                    ->label('Область меню')
                    ->options(NavigationMenuItem::locationOptions())
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('label')
                    ->label('Текст пункта')
                    ->required()
                    ->maxLength(120),
                Forms\Components\TextInput::make('path')
                    ->label('Ссылка')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Для внутренних страниц используйте путь вида /catalog, для внешних — полный URL.'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Порядок')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_external')
                    ->label('Внешняя ссылка')
                    ->default(false),
                Forms\Components\Toggle::make('open_in_new_tab')
                    ->label('Открывать в новой вкладке')
                    ->default(false),
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
