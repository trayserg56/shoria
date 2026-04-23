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
            ])
            ->columns(2);
    }
}
