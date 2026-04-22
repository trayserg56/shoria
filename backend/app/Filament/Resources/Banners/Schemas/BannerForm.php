<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('subtitle')
                    ->maxLength(255),
                Forms\Components\TextInput::make('cta_label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('cta_url')
                    ->maxLength(255)
                    ->placeholder('/catalog или /news/slug-materiala')
                    ->helperText('Можно указать внутренний путь (например, /news/white-sneakers-care) или внешний URL.'),
                Forms\Components\TextInput::make('image_url')
                    ->label('URL изображения')
                    ->maxLength(2048),
                Forms\Components\FileUpload::make('image_file')
                    ->label('Или загрузить изображение')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (is_string($state) && trim($state) !== '') {
                            $set('image_url', $state);
                        }
                    })
                    ->helperText('Можно оставить URL выше или загрузить файл с компьютера.'),
                Forms\Components\TextInput::make('bg_color')
                    ->maxLength(24),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ])
            ->columns(2);
    }
}
