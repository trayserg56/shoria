<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentWebhookLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentWebhookLogs';

    protected static ?string $title = 'Webhook-события оплаты';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('provider_code')
                    ->label('Провайдер'),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Событие')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус обработки'),
                Tables\Columns\TextColumn::make('provider_payment_id')
                    ->label('ID у провайдера')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Ошибка')
                    ->limit(80)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
