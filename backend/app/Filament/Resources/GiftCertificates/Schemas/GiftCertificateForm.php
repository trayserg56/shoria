<?php

namespace App\Filament\Resources\GiftCertificates\Schemas;

use App\Models\GiftCertificate;
use Filament\Forms;
use Filament\Schemas\Schema;

class GiftCertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('owner_user_id')
                    ->label('Владелец (личный кабинет)')
                    ->relationship('owner', 'email')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('code')
                    ->label('Код')
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Оставьте пустым — будет сгенерирован автоматически.')
                    ->disabledOn('edit')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Forms\Components\TextInput::make('initial_amount')
                    ->label('Номинал (₽)')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('balance_remaining')
                    ->label('Остаток (₽)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        GiftCertificate::STATUS_ACTIVE => 'Активен',
                        GiftCertificate::STATUS_DEPLETED => 'Исчерпан',
                        GiftCertificate::STATUS_CANCELLED => 'Аннулирован',
                    ])
                    ->required()
                    ->hiddenOn('create')
                    ->disabled(fn (?GiftCertificate $record): bool => $record?->status === GiftCertificate::STATUS_DEPLETED),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Действителен до')
                    ->seconds(false)
                    ->native(false),
                Forms\Components\TextInput::make('recipient_email')
                    ->label('Email получателя (необязательно)')
                    ->email()
                    ->maxLength(255)
                    ->helperText('Если указан, сертификат применим только при оформлении с этим email.'),
                Forms\Components\Textarea::make('admin_note')
                    ->label('Внутренняя заметка')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
