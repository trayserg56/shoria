<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Товар')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('sku')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Textarea::make('description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0),
                                Forms\Components\TextInput::make('old_price')
                                    ->numeric()
                                    ->minValue(0),
                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'RUB' => 'RUB',
                                        'USD' => 'USD',
                                        'EUR' => 'EUR',
                                    ])
                                    ->default('RUB')
                                    ->required(),
                                Forms\Components\TextInput::make('stock')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                Forms\Components\TextInput::make('sort_order')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_featured')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_hit')
                                    ->label('Тег: Хит')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_new')
                                    ->label('Тег: Новинка')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_customer_choice')
                                    ->label('Тег: Выбор покупателей')
                                    ->default(false),
                                Forms\Components\Repeater::make('images')
                                    ->relationship('images')
                                    ->schema([
                                        Forms\Components\TextInput::make('url')
                                            ->label('URL изображения')
                                            ->requiredWithout('image_file')
                                            ->maxLength(2048),
                                        Forms\Components\FileUpload::make('image_file')
                                            ->label('Или загрузить изображение')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('products')
                                            ->visibility('public')
                                            ->requiredWithout('url')
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                if (is_string($state) && trim($state) !== '') {
                                                    $set('url', $state);
                                                }
                                            }),
                                        Forms\Components\TextInput::make('alt')
                                            ->maxLength(255),
                                        Forms\Components\Toggle::make('is_cover')
                                            ->default(false),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO title')
                                    ->maxLength(255)
                                    ->helperText('Если пусто, витрина соберет title из названия товара и категории.'),
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
