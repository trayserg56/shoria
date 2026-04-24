<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Категория')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\Select::make('parent_id')
                                    ->label('Родительская категория')
                                    ->options(function ($record): array {
                                        return Category::query()
                                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->whereNull('parent_id')
                                            ->orderBy('sort_order')
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('image_url')
                                    ->label('URL изображения')
                                    ->maxLength(2048),
                                Forms\Components\FileUpload::make('image_file')
                                    ->label('Или загрузить изображение')
                                    ->image()
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth(1920)
                                    ->imageResizeTargetHeight(1920)
                                    ->imageResizeUpscale(false)
                                    ->disk('public')
                                    ->directory('categories')
                                    ->visibility('public')
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (is_string($state) && trim($state) !== '') {
                                            $set('image_url', $state);
                                        }
                                    })
                                    ->helperText('Можно оставить URL выше или загрузить файл (до 2MB). Изображение будет оптимизировано.'),
                                Forms\Components\Toggle::make('is_featured')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активна на витрине')
                                    ->default(true),
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                            ])
                            ->columns(2),
                        Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO title')
                                    ->maxLength(255)
                                    ->helperText('Если пусто, на витрине будет использоваться название категории.'),
                                Forms\Components\Textarea::make('seo_description')
                                    ->label('SEO description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
