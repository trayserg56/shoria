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
                \Filament\Schemas\Components\Section::make('Служебное')
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
                    ->columnSpanFull()
                    ->visible(fn ($record): bool => $record !== null),
            ])
            ->columns(2);
    }
}
