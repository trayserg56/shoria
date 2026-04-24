<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Заказ')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Покупатель')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Legacy')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'new' => 'Новый',
                        'paid' => 'Оплачен',
                        'cancelled' => 'Отменен',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('order_status')
                    ->label('Заказ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'placed' => 'Оформлен',
                        'confirmed' => 'Подтвержден',
                        'completed' => 'Завершен',
                        'cancelled' => 'Отменен',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'confirmed' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Оплата')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'unpaid' => 'Не начата',
                        'pending' => 'Ожидает',
                        'authorized' => 'Холд',
                        'paid' => 'Оплачен',
                        'failed' => 'Ошибка',
                        'cancelled' => 'Отменен',
                        'partially_refunded' => 'Частичный возврат',
                        'refunded' => 'Возвращен',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'paid', 'authorized' => 'success',
                        'failed', 'cancelled' => 'danger',
                        'pending' => 'warning',
                        'refunded', 'partially_refunded' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fulfillment_status')
                    ->label('Исполнение')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Ожидает',
                        'processing' => 'Сборка',
                        'packed' => 'Упакован',
                        'shipped' => 'В доставке',
                        'ready_for_pickup' => 'Готов к выдаче',
                        'delivered' => 'Доставлен',
                        'returned' => 'Возврат',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'delivered' => 'success',
                        'returned' => 'danger',
                        'processing', 'packed', 'shipped', 'ready_for_pickup' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('delivery_method')
                    ->label('Доставка')
                    ->formatStateUsing(fn (string $state) => $state === 'courier' ? 'Курьер' : 'Самовывоз'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Оплата')
                    ->formatStateUsing(fn (string $state) => $state === 'card' ? 'Карта' : 'Наличные')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Сумма')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0, '.', ' ') . ' ' . $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Оформлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.email')
                    ->label('Создал')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('updatedBy.email')
                    ->label('Изменил')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Legacy status')
                    ->options([
                        'new' => 'Новый',
                        'paid' => 'Оплачен',
                        'cancelled' => 'Отменен',
                        'processing' => 'В обработке',
                        'completed' => 'Завершен',
                    ]),
                Tables\Filters\SelectFilter::make('order_status')
                    ->label('Статус заказа')
                    ->options([
                        'placed' => 'Оформлен',
                        'confirmed' => 'Подтвержден',
                        'completed' => 'Завершен',
                        'cancelled' => 'Отменен',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Статус оплаты')
                    ->options([
                        'unpaid' => 'Не начата',
                        'pending' => 'Ожидает',
                        'authorized' => 'Холд',
                        'paid' => 'Оплачен',
                        'failed' => 'Ошибка',
                        'cancelled' => 'Отменен',
                        'partially_refunded' => 'Частичный возврат',
                        'refunded' => 'Возвращен',
                    ]),
                Tables\Filters\SelectFilter::make('delivery_method')
                    ->label('Доставка')
                    ->options([
                        'courier' => 'Курьер',
                        'pickup' => 'Самовывоз',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Оплата')
                    ->options([
                        'card' => 'Карта',
                        'cash' => 'Наличные',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Открыть'),
            ])
            ->defaultSort('placed_at', 'desc');
    }
}
