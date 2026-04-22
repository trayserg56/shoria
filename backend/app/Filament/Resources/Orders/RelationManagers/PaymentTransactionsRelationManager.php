<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentTransactions';

    protected static ?string $title = 'Платежные транзакции';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Провайдер'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Драйвер'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('provider_payment_id')
                    ->label('ID у провайдера')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
