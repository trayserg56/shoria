<?php

namespace App\Filament\Resources\SiteSettings\Schemas;

use App\Models\SiteSetting;
use App\Support\Store\StoreFeatureFlags;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Шапка и контакты')
                    ->description('Отображаются на публичной витрине (лого, телефон, часы).')
                    ->schema([
                        Forms\Components\TextInput::make('logo_text')
                            ->label('Название магазина (текст)')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Показывается в шапке, если не загружен файл логотипа.'),

                        Forms\Components\FileUpload::make('logo_image_path')
                            ->label('Логотип (изображение)')
                            ->image()
                            ->maxSize(1024)
                            ->acceptedFileTypes(['image/svg+xml', 'image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth(480)
                            ->imageResizeTargetHeight(160)
                            ->imageResizeUpscale(false)
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public')
                            ->nullable()
                            ->helperText('PNG/SVG/WebP до 1 МБ. Если задано, в шапке показывается картинка вместо текста.'),

                        Forms\Components\TextInput::make('phone_display')
                            ->label('Телефон (как показывать)')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('+7 (900) 000-00-00'),

                        Forms\Components\TextInput::make('phone_tel')
                            ->label('Телефон для ссылки (tel:)')
                            ->required()
                            ->maxLength(32)
                            ->placeholder('+79000000000')
                            ->helperText('Те же цифры в формате для кликабельного звонка, например +79001234567.'),

                        Forms\Components\TextInput::make('work_hours_short')
                            ->label('График работы (кратко)')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Пн–Вс: 10:00–20:00'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Подвал и контакты')
                    ->description('Опционально: email для покупателей и текст у копирайта.')
                    ->schema([
                        Forms\Components\TextInput::make('support_email')
                            ->label('Email поддержки')
                            ->email()
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\TextInput::make('footer_legal_line')
                            ->label('Строка у копирайта')
                            ->maxLength(500)
                            ->nullable()
                            ->helperText('Если пусто — используется стандартная фраза © …'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Возможности витрины')
                    ->description('Отключённые флаги скрывают блоки на сайте и отключают соответствующую логику в оформлении заказа.')
                    ->schema(
                        collect(StoreFeatureFlags::keys())
                            ->map(fn (string $key): Forms\Components\Toggle => Forms\Components\Toggle::make('feature_flags.'.$key)
                                ->label(StoreFeatureFlags::labels()[$key])
                                ->default(true))
                            ->all(),
                    )
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Служебное')
                    ->schema([
                        Forms\Components\Placeholder::make('created_by_info')
                            ->label('Создал')
                            ->content(fn (?SiteSetting $record): string => $record?->createdBy?->email ?? '—'),
                        Forms\Components\Placeholder::make('updated_by_info')
                            ->label('Изменил')
                            ->content(fn (?SiteSetting $record): string => $record?->updatedBy?->email ?? '—'),
                        Forms\Components\Placeholder::make('created_at_info')
                            ->label('Дата создания')
                            ->content(fn (?SiteSetting $record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                        Forms\Components\Placeholder::make('updated_at_info')
                            ->label('Дата изменения')
                            ->content(fn (?SiteSetting $record): string => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (?SiteSetting $record): bool => $record !== null),
            ]);
    }
}
