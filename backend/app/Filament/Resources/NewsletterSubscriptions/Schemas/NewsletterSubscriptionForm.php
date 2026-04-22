<?php

namespace App\Filament\Resources\NewsletterSubscriptions\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class NewsletterSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->disabled(),
                Forms\Components\TextInput::make('source')
                    ->label('Источник')
                    ->maxLength(64),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'subscribed' => 'Подписан',
                        'unsubscribed' => 'Отписан',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('subscribed_at')
                    ->label('Дата подписки')
                    ->seconds(false),
                Forms\Components\DateTimePicker::make('unsubscribed_at')
                    ->label('Дата отписки')
                    ->seconds(false),
                Forms\Components\TextInput::make('ip_address')
                    ->label('IP')
                    ->disabled(),
                Forms\Components\Textarea::make('user_agent')
                    ->label('User-Agent')
                    ->rows(3)
                    ->disabled()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}

