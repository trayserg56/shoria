<?php

namespace App\Filament\Resources\ServicePages\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ServicePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Служебная страница')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Заголовок')
                                    ->required()
                                    ->maxLength(160),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(180)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Путь страницы будет вида: /pages/{slug}.'),
                                Forms\Components\Textarea::make('excerpt')
                                    ->label('Краткое описание')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('content')
                                    ->label('Контент')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('service-pages')
                                    ->fileAttachmentsVisibility('public')
                                    ->fileAttachmentsMaxSize(2048)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Порядок')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Активна')
                                    ->default(true),
                                Forms\Components\Toggle::make('show_in_header')
                                    ->label('Показывать в шапке')
                                    ->default(false),
                                Forms\Components\Toggle::make('show_in_footer')
                                    ->label('Показывать в футере')
                                    ->default(true),
                            ])
                            ->columns(2),
                        Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO title')
                                    ->maxLength(255)
                                    ->helperText('Если пусто, будет использован заголовок страницы.'),
                                Forms\Components\Textarea::make('seo_description')
                                    ->label('SEO description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Служебное')
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
                            ->visible(fn ($record): bool => $record !== null),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}

