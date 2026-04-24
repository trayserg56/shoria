<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Промокод')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(120),
                                Forms\Components\TextInput::make('code')
                                    ->label('Код')
                                    ->required()
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('discount_type')
                                    ->label('Тип скидки')
                                    ->options([
                                        'fixed_percent' => 'Процент',
                                        'fixed_amount' => 'Фиксированная сумма',
                                    ])
                                    ->required()
                                    ->default('fixed_percent'),
                                Forms\Components\TextInput::make('discount_value')
                                    ->label('Значение скидки')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\Select::make('applies_to')
                                    ->label('Применять к')
                                    ->options([
                                        'order' => 'Весь заказ',
                                        'items' => 'Только к подходящим товарам',
                                    ])
                                    ->default('order')
                                    ->live()
                                    ->required(),
                                Forms\Components\Select::make('items_match_mode')
                                    ->label('Логика условий для товаров')
                                    ->options([
                                        'any' => 'Любое условие (ИЛИ)',
                                        'all' => 'Все условия (И)',
                                    ])
                                    ->default('any')
                                    ->required()
                                    ->disabled(fn (callable $get): bool => $get('applies_to') !== 'items'),
                                Forms\Components\Toggle::make('free_delivery')
                                    ->label('Бесплатная доставка')
                                    ->default(false)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),
                            ])
                            ->columns(2),
                        Tab::make('Действия и условия')
                            ->schema([
                                Forms\Components\TextInput::make('min_subtotal')
                                    ->label('Минимальная сумма заказа')
                                    ->numeric()
                                    ->minValue(0),
                                Forms\Components\TextInput::make('min_items_count')
                                    ->label('Минимум товаров (шт.)')
                                    ->numeric()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('max_discount_amount')
                                    ->label('Макс. размер скидки')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Ограничивает скидку сверху, например 1500 ₽.'),
                                Forms\Components\Toggle::make('first_order_only')
                                    ->label('Только для первого заказа')
                                    ->default(false),
                                Forms\Components\Select::make('included_product_ids')
                                    ->label('Товары-участники')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->helperText('Если пусто — ограничений по товарам нет.')
                                    ->disabled(fn (callable $get): bool => $get('applies_to') !== 'items')
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('included_category_ids')
                                    ->label('Категории-участники')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->disabled(fn (callable $get): bool => $get('applies_to') !== 'items'),
                                Forms\Components\Select::make('included_brand_ids')
                                    ->label('Бренды-участники')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->disabled(fn (callable $get): bool => $get('applies_to') !== 'items'),
                            ])
                            ->columns(2),
                        Tab::make('Ограничения')
                            ->schema([
                                Forms\Components\TextInput::make('usage_limit')
                                    ->label('Лимит использований')
                                    ->numeric()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('used_count')
                                    ->label('Уже использован')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Действует с'),
                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('Действует до'),
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
}
