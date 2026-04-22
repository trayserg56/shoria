<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';

    protected static ?string $title = 'История статусов';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('order_id', $this->getOwnerRecord()->getKey()))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('field')
                    ->label('Контур')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'order_status' => 'Заказ',
                        'payment_status' => 'Оплата',
                        'fulfillment_status' => 'Исполнение',
                        'refund_status' => 'Возврат',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('old_value')
                    ->label('Было')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('new_value')
                    ->label('Стало'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Источник'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
