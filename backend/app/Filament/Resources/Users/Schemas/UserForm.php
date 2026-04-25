<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(120),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('phone')
                    ->label('Телефон')
                    ->maxLength(30),
                Forms\Components\TextInput::make('loyalty_points_balance')
                    ->label('Баланс баллов')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('loyalty_total_spent')
                    ->label('Сумма покупок для уровней (₽)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\Select::make('role')
                    ->label('Роль')
                    ->options(User::roleOptions())
                    ->required()
                    ->default(User::ROLE_CUSTOMER),
                Forms\Components\TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->maxLength(120)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Forms\Components\DateTimePicker::make('email_verified_at')
                    ->label('Email подтвержден')
                    ->seconds(false),
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
