<?php

namespace App\Filament\Resources\NewsPosts\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class NewsPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Новость')
                    ->tabs([
                        Tab::make('Основное')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('content_type')
                                    ->label('Тип материала')
                                    ->options([
                                        'news' => 'Новость',
                                        'guide' => 'Гайд',
                                        'collection' => 'Подборка',
                                        'promo' => 'Промо',
                                    ])
                                    ->default('news')
                                    ->required(),
                                Forms\Components\Textarea::make('excerpt')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('products')
                                    ->label('Связанные товары')
                                    ->relationship('products', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Эти товары будут показаны в CTA-блоке на странице материала. Если ничего не выбрано, витрина использует автоподбор.')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('cover_url')
                                    ->label('URL обложки')
                                    ->maxLength(2048),
                                Forms\Components\FileUpload::make('cover_file')
                                    ->label('Или загрузить обложку')
                                    ->image()
                                    ->maxSize(2048)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth(1920)
                                    ->imageResizeTargetHeight(1920)
                                    ->imageResizeUpscale(false)
                                    ->disk('public')
                                    ->directory('news-covers')
                                    ->visibility('public')
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (is_string($state) && trim($state) !== '') {
                                            $set('cover_url', $state);
                                        }
                                    })
                                    ->helperText('Можно оставить URL выше или загрузить файл (до 2MB). Изображение будет оптимизировано.'),
                                Forms\Components\DateTimePicker::make('published_at'),
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Показывать на витрине')
                                    ->default(false),
                                Forms\Components\RichEditor::make('content')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('news-content')
                                    ->fileAttachmentsVisibility('public')
                                    ->fileAttachmentsMaxSize(2048)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO title')
                                    ->maxLength(255)
                                    ->helperText('Если пусто, будет использоваться заголовок новости.'),
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
