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
                                    ->label('Основная категория')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('categories')
                                    ->label('Дополнительные категории и подкатегории')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Товар может принадлежать сразу нескольким категориям.'),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('brand_id')
                                    ->label('Бренд')
                                    ->relationship('brandEntity', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Название бренда')
                                            ->required()
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->maxLength(140),
                                    ]),
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
                                    ->label('Активен на витрине')
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
                                Forms\Components\FileUpload::make('images_bulk_upload')
                                    ->label('Загрузить изображения (массово)')
                                    ->image()
                                    ->multiple()
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth(1920)
                                    ->imageResizeTargetHeight(1920)
                                    ->imageResizeUpscale(false)
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                        $uploads = is_array($state)
                                            ? array_values(array_filter($state, fn ($value): bool => is_string($value) && trim($value) !== ''))
                                            : [];

                                        if ($uploads === []) {
                                            return;
                                        }

                                        $existing = $get('images');
                                        $items = is_array($existing) ? $existing : [];
                                        $currentMaxSort = 0;

                                        foreach ($items as $item) {
                                            $currentMaxSort = max($currentMaxSort, (int) ($item['sort_order'] ?? 0));
                                        }

                                        $hasCover = collect($items)->contains(fn ($item): bool => (bool) ($item['is_cover'] ?? false));

                                        foreach ($uploads as $path) {
                                            $currentMaxSort++;
                                            $items[] = [
                                                'url' => $path,
                                                'alt' => null,
                                                'is_cover' => ! $hasCover,
                                                'sort_order' => $currentMaxSort,
                                            ];

                                            $hasCover = true;
                                        }

                                        $set('images', $items);
                                        $set('images_bulk_upload', []);
                                    })
                                    ->helperText('Можно выбрать сразу несколько изображений. Лимит: 2MB на файл.'),
                                Forms\Components\Repeater::make('images')
                                    ->relationship('images')
                                    ->label('Галерея')
                                    ->addable(false)
                                    ->schema([
                                        Forms\Components\TextInput::make('url')
                                            ->label('URL изображения')
                                            ->required()
                                            ->maxLength(2048),
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
