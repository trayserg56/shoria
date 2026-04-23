<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Размеры и варианты';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('size_label')
                    ->label('Размер')
                    ->required()
                    ->maxLength(32),
                Forms\Components\TextInput::make('color_label')
                    ->label('Цвет')
                    ->maxLength(64),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug варианта')
                    ->maxLength(120)
                    ->helperText('Если оставить пустым, slug сгенерируется автоматически.'),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU варианта')
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->label('Цена (опционально)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('stock')
                    ->label('Остаток')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
                Forms\Components\FileUpload::make('images_bulk_upload')
                    ->label('Загрузить изображения варианта (массово)')
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
                    ->directory('products/variants')
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
                    ->label('Изображения варианта (опционально)')
                    ->addable(false)
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->label('URL изображения')
                            ->required()
                            ->maxLength(2048),
                        Forms\Components\TextInput::make('alt')
                            ->label('Alt')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_cover')
                            ->label('Обложка')
                            ->default(false),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('size_label')
                    ->label('Размер')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('color_label')
                    ->label('Цвет')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state, $record) => $state !== null
                        ? number_format((float) $state, 0, '.', ' ') . ' ' . $record->product->currency
                        : 'Базовая'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Остаток')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
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
