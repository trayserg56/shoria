<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Заказ')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label('Номер заказа')
                                    ->disabled(),
                                Forms\Components\Select::make('order_status')
                                    ->label('Статус заказа')
                                    ->options([
                                        'placed' => 'Оформлен',
                                        'confirmed' => 'Подтвержден',
                                        'completed' => 'Завершен',
                                        'cancelled' => 'Отменен',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('payment_status')
                                    ->label('Статус оплаты')
                                    ->options([
                                        'unpaid' => 'Не начата',
                                        'pending' => 'Ожидает подтверждения',
                                        'authorized' => 'Холд',
                                        'paid' => 'Оплачен',
                                        'failed' => 'Ошибка',
                                        'cancelled' => 'Отменен',
                                        'partially_refunded' => 'Частичный возврат',
                                        'refunded' => 'Возвращен',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('fulfillment_status')
                                    ->label('Статус исполнения')
                                    ->options([
                                        'pending' => 'Ожидает обработки',
                                        'processing' => 'Сборка',
                                        'packed' => 'Упакован',
                                        'shipped' => 'Передан в доставку',
                                        'ready_for_pickup' => 'Готов к выдаче',
                                        'delivered' => 'Доставлен',
                                        'returned' => 'Возврат в магазин',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('refund_status')
                                    ->label('Статус возврата')
                                    ->options([
                                        'none' => 'Нет',
                                        'requested' => 'Запрошен',
                                        'approved' => 'Подтвержден',
                                        'processing' => 'В обработке',
                                        'partially_refunded' => 'Частичный возврат',
                                        'refunded' => 'Возвращен',
                                        'failed' => 'Ошибка',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('status')
                                    ->label('Legacy status')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('placed_at')
                                    ->label('Дата оформления')
                                    ->seconds(false)
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('confirmed_at')
                                    ->label('Подтвержден')
                                    ->seconds(false)
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('cancelled_at')
                                    ->label('Отменен')
                                    ->seconds(false)
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('completed_at')
                                    ->label('Завершен')
                                    ->seconds(false)
                                    ->disabled(),
                                Forms\Components\TextInput::make('currency')
                                    ->label('Валюта')
                                    ->disabled(),
                                Forms\Components\TextInput::make('delivery_method')
                                    ->label('Доставка')
                                    ->disabled(),
                                Forms\Components\TextInput::make('payment_method')
                                    ->label('Оплата')
                                    ->disabled(),
                                Forms\Components\TextInput::make('promo_code')
                                    ->label('Промокод')
                                    ->disabled(),
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Подытог')
                                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                                    ->disabled(),
                                Forms\Components\TextInput::make('discount_total')
                                    ->label('Скидка')
                                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                                    ->disabled(),
                                Forms\Components\TextInput::make('loyalty_points_spent')
                                    ->label('Списано баллов')
                                    ->disabled(),
                                Forms\Components\TextInput::make('loyalty_discount_total')
                                    ->label('Скидка баллами')
                                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                                    ->disabled(),
                                Forms\Components\TextInput::make('loyalty_points_earned')
                                    ->label('Начислено баллов')
                                    ->disabled(),
                                Forms\Components\TextInput::make('delivery_total')
                                    ->label('Доставка, сумма')
                                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                                    ->disabled(),
                                Forms\Components\TextInput::make('total')
                                    ->label('Итого')
                                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                                    ->disabled(),
                                Forms\Components\Textarea::make('comment')
                                    ->label('Комментарий покупателя')
                                    ->rows(4)
                                    ->disabled()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Покупатель')
                            ->schema([
                                Forms\Components\Placeholder::make('customer_account')
                                    ->label('Аккаунт')
                                    ->content(fn ($record) => $record->user?->name
                                        ? $record->user->name . ' (' . $record->user->email . ')'
                                        : 'Гостевой заказ'),
                                Forms\Components\TextInput::make('customer_name')
                                    ->label('Имя')
                                    ->disabled(),
                                Forms\Components\TextInput::make('customer_email')
                                    ->label('Email')
                                    ->disabled(),
                                Forms\Components\TextInput::make('customer_phone')
                                    ->label('Телефон')
                                    ->disabled(),
                                Forms\Components\TextInput::make('session_id')
                                    ->label('Session ID')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Операции')
                            ->schema([
                                Forms\Components\Placeholder::make('status_model_note')
                                    ->label('Операционная модель')
                                    ->content('Эта карточка уже использует отдельные статусы заказа, оплаты, исполнения и возврата. Legacy status сохраняется для обратной совместимости API и старого UI.'),
                            ]),
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
