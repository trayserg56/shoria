<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use App\Models\ProductReview;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Отзыв')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('ID отзыва')
                                    ->disabled(),
                                Forms\Components\Select::make('product_id')
                                    ->label('Товар')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),
                                Forms\Components\Select::make('user_id')
                                    ->label('Пользователь')
                                    ->relationship('user', 'email')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),
                                Forms\Components\TextInput::make('rating')
                                    ->label('Оценка')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5)
                                    ->required(),
                                Forms\Components\Textarea::make('review_text')
                                    ->label('Текст отзыва')
                                    ->rows(6)
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Показывать на витрине')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_verified_purchase')
                                    ->label('Проверенная покупка')
                                    ->default(false),
                            ])
                            ->columns(2),
                        Tab::make('Автор и покупка')
                            ->schema([
                                Forms\Components\Placeholder::make('user_info')
                                    ->label('Кто написал')
                                    ->content(function (?ProductReview $record): string {
                                        if ($record === null) {
                                            return '—';
                                        }

                                        $name = $record->user?->name ?: 'Без имени';
                                        $email = $record->user?->email ?: 'email не указан';
                                        $phone = $record->user?->phone ?: 'телефон не указан';

                                        return sprintf('%s, %s, %s', $name, $email, $phone);
                                    }),
                                Forms\Components\Placeholder::make('product_info')
                                    ->label('Что купил')
                                    ->content(function (?ProductReview $record): string {
                                        if ($record === null) {
                                            return '—';
                                        }

                                        $name = $record->product?->name
                                            ?? $record->orderItem?->product_name
                                            ?? 'Товар удален';
                                        $slug = $record->product?->slug
                                            ?? $record->orderItem?->product_slug
                                            ?? null;

                                        return $slug ? sprintf('%s (%s)', $name, $slug) : $name;
                                    }),
                                Forms\Components\Placeholder::make('order_info')
                                    ->label('Заказ')
                                    ->content(function (?ProductReview $record): string {
                                        if ($record === null) {
                                            return '—';
                                        }

                                        $orderNumber = $record->orderItem?->order?->order_number;
                                        $variant = $record->orderItem?->variant_label;

                                        if ($orderNumber === null && $variant === null) {
                                            return '—';
                                        }

                                        if ($orderNumber !== null && $variant !== null) {
                                            return sprintf('%s, вариант: %s', $orderNumber, $variant);
                                        }

                                        if ($orderNumber !== null) {
                                            return $orderNumber;
                                        }

                                        return sprintf('Вариант: %s', $variant);
                                    }),
                                Forms\Components\Placeholder::make('verified_info')
                                    ->label('Основание проверки')
                                    ->content(function (?ProductReview $record): string {
                                        if ($record === null) {
                                            return '—';
                                        }

                                        return $record->order_item_id !== null
                                            ? 'Есть привязка к позиции заказа'
                                            : 'Без привязки к позиции заказа';
                                    }),
                            ])
                            ->columns(2),
                        Tab::make('Служебное')
                            ->schema([
                                Forms\Components\Placeholder::make('created_at_info')
                                    ->label('Дата создания')
                                    ->content(fn (?ProductReview $record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('updated_at_info')
                                    ->label('Дата изменения')
                                    ->content(fn (?ProductReview $record): string => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
                            ])
                            ->columns(2)
                            ->visible(fn (?ProductReview $record): bool => $record !== null),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
