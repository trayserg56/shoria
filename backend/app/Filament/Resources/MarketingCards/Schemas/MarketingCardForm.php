<?php

namespace App\Filament\Resources\MarketingCards\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MarketingCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('label')
                    ->label('Метка над заголовком')
                    ->maxLength(120)
                    ->placeholder('Например: Туризм, Новинка'),
                Forms\Components\TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('link_url')
                    ->label('Ссылка карточки')
                    ->required()
                    ->maxLength(2048)
                    ->placeholder('/catalog/razdel или /news/slug или https://…')
                    ->helperText('Одна ссылка на всю карточку: клик по области баннера ведёт сюда.'),
                Forms\Components\Textarea::make('lines_text')
                    ->label('Подписи под заголовком')
                    ->rows(4)
                    ->placeholder("от 5 990 ₽\nДоставка от 1 дня\nКак на витринах топ-брендов")
                    ->helperText('Одна или несколько строк — только текст, без отдельных ссылок. Каждая строка показывается отдельной надписью.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('image_url')
                    ->label('URL изображения')
                    ->maxLength(2048),
                Forms\Components\FileUpload::make('image_file')
                    ->label('Или загрузить изображение')
                    ->image()
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->imageEditor()
                    ->imageResizeMode('cover')
                    ->imageResizeTargetWidth(1200)
                    ->imageResizeTargetHeight(800)
                    ->imageResizeUpscale(false)
                    ->disk('public')
                    ->directory('marketing-cards')
                    ->visibility('public')
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (is_string($state) && trim($state) !== '') {
                            $set('image_url', $state);
                        }
                    })
                    ->helperText('Фон карточки на главной. Можно указать URL выше или загрузить файл (до 2MB).'),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
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
