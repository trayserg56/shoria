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
                                        $categories = Category::query()
                                            ->orderBy('sort_order')
                                            ->orderBy('name')
                                            ->get(['id', 'parent_id', 'name']);

                                        $excludedIds = $record
                                            ? self::collectDescendantIds($categories, (int) $record->getKey())
                                            : [];

                                        if ($record) {
                                            $excludedIds[] = (int) $record->getKey();
                                        }

                                        return self::buildHierarchyOptions($categories, $excludedIds);
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
                        Tab::make('Служебное')
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
                            ->visible(fn ($record): bool => $record !== null),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Category> $categories
     * @return array<int, string>
     */
    protected static function buildHierarchyOptions($categories, array $excludedIds = []): array
    {
        $excludedLookup = array_fill_keys($excludedIds, true);
        $childrenByParent = [];

        foreach ($categories as $category) {
            $childrenByParent[$category->parent_id ?? 0][] = $category;
        }

        $appendOptions = function (int $parentId, int $depth, array &$options) use (&$appendOptions, $childrenByParent, $excludedLookup): void {
            foreach ($childrenByParent[$parentId] ?? [] as $child) {
                $id = (int) $child->id;

                if (! isset($excludedLookup[$id])) {
                    $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                    $options[$id] = $prefix.$child->name;
                }

                $appendOptions($id, $depth + 1, $options);
            }
        };

        $options = [];
        $appendOptions(0, 0, $options);

        return $options;
    }

    /**
     * @param \Illuminate\Support\Collection<int, Category> $categories
     * @return int[]
     */
    protected static function collectDescendantIds($categories, int $rootId): array
    {
        $childrenByParent = [];

        foreach ($categories as $category) {
            $childrenByParent[$category->parent_id ?? 0][] = (int) $category->id;
        }

        $result = [];
        $stack = [$rootId];

        while ($stack !== []) {
            $parentId = array_pop($stack);

            foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                if (in_array($childId, $result, true)) {
                    continue;
                }

                $result[] = $childId;
                $stack[] = $childId;
            }
        }

        return $result;
    }
}
