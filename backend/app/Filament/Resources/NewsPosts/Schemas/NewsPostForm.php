<?php

namespace App\Filament\Resources\NewsPosts\Schemas;

use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
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
                                    ->label('Заголовок')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('content_type')
                                    ->label('Тип материала')
                                    ->options([
                                        'news'       => 'Новость',
                                        'guide'      => 'Гайд',
                                        'collection' => 'Подборка',
                                        'promo'      => 'Промо',
                                    ])
                                    ->default('news')
                                    ->required(),
                                Forms\Components\Textarea::make('excerpt')
                                    ->label('Анонс')
                                    ->rows(3)
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
                                    ->helperText('До 2MB, формат JPEG/PNG/WebP.'),
                                Forms\Components\Select::make('products')
                                    ->label('Связанные товары')
                                    ->relationship('products', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Показываются в CTA-блоке страницы. Если пусто — автоподбор.')
                                    ->columnSpanFull(),
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Дата публикации'),
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Показывать на витрине')
                                    ->default(false),
                            ])
                            ->columns(2),

                        Tab::make('Конструктор блоков')
                            ->schema([
                                Forms\Components\Placeholder::make('blocks_hint')
                                    ->label('')
                                    ->content('Структурируйте статью из блоков. Если блоки заданы — вкладка «Устаревший контент» игнорируется.')
                                    ->columnSpanFull(),

                                Builder::make('blocks')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable()
                                    ->blocks([

                                        Block::make('text')
                                            ->label('Текст')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                TiptapEditor::make('content')
                                                    ->label('Контент')
                                                    ->profile('default')
                                                    ->directory('news-content')
                                                    ->columnSpanFull()
                                                    ->required(),
                                            ]),

                                        Block::make('callout')
                                            ->label('Выноска')
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                Forms\Components\Select::make('type')
                                                    ->label('Тип')
                                                    ->options([
                                                        'info'    => '💡 Информация',
                                                        'tip'     => '✅ Совет',
                                                        'warning' => '⚠️ Предупреждение',
                                                        'danger'  => '🚫 Важно',
                                                    ])
                                                    ->default('info')
                                                    ->required(),
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок (необязательно)')
                                                    ->maxLength(160),
                                                Forms\Components\Textarea::make('text')
                                                    ->label('Текст')
                                                    ->rows(3)
                                                    ->required()
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),

                                        Block::make('checklist')
                                            ->label('Список преимуществ')
                                            ->icon('heroicon-o-check-badge')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Пункты')
                                                    ->columnSpanFull()
                                                    ->reorderable()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('text')
                                                            ->label('Текст пункта')
                                                            ->required()
                                                            ->maxLength(300),
                                                        Forms\Components\TextInput::make('note')
                                                            ->label('Уточнение')
                                                            ->maxLength(300),
                                                    ])
                                                    ->columns(2)
                                                    ->minItems(1),
                                            ]),

                                        Block::make('steps')
                                            ->label('Пошаговая инструкция')
                                            ->icon('heroicon-o-list-bullet')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Шаги')
                                                    ->columnSpanFull()
                                                    ->reorderable()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('title')
                                                            ->label('Название шага')
                                                            ->required()
                                                            ->maxLength(200),
                                                        Forms\Components\Textarea::make('description')
                                                            ->label('Описание')
                                                            ->rows(2)
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->minItems(1),
                                            ]),

                                        Block::make('image')
                                            ->label('Изображение')
                                            ->icon('heroicon-o-photo')
                                            ->schema([
                                                Forms\Components\TextInput::make('src')
                                                    ->label('URL изображения')
                                                    ->url()
                                                    ->required()
                                                    ->columnSpanFull(),
                                                Forms\Components\TextInput::make('alt')
                                                    ->label('Alt-текст')
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('caption')
                                                    ->label('Подпись')
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('link')
                                                    ->label('Ссылка (необязательно)')
                                                    ->url()
                                                    ->maxLength(255),
                                                Forms\Components\Select::make('size')
                                                    ->label('Ширина')
                                                    ->options([
                                                        'full'   => 'Полная',
                                                        'wide'   => 'Широкая (80%)',
                                                        'medium' => 'Средняя (60%)',
                                                        'small'  => 'Маленькая (40%)',
                                                    ])
                                                    ->default('full'),
                                            ])
                                            ->columns(2),

                                        Block::make('cta')
                                            ->label('Призыв к действию')
                                            ->icon('heroicon-o-cursor-arrow-rays')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок')
                                                    ->maxLength(200)
                                                    ->columnSpanFull(),
                                                Forms\Components\Textarea::make('text')
                                                    ->label('Текст')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('buttons')
                                                    ->label('Кнопки')
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('label')
                                                            ->label('Текст')
                                                            ->required()
                                                            ->maxLength(80),
                                                        Forms\Components\TextInput::make('url')
                                                            ->label('Ссылка')
                                                            ->required()
                                                            ->maxLength(255),
                                                        Forms\Components\Select::make('variant')
                                                            ->label('Стиль')
                                                            ->options([
                                                                'primary' => 'Основная',
                                                                'outline' => 'Контурная',
                                                                'ghost'   => 'Призрак',
                                                            ])
                                                            ->default('primary'),
                                                    ])
                                                    ->columns(3)
                                                    ->minItems(1),
                                                Forms\Components\Select::make('align')
                                                    ->label('Выравнивание')
                                                    ->options([
                                                        'left'   => 'Слева',
                                                        'center' => 'По центру',
                                                        'right'  => 'Справа',
                                                    ])
                                                    ->default('left'),
                                            ]),

                                        Block::make('columns')
                                            ->label('Карточки в сетке')
                                            ->icon('heroicon-o-squares-2x2')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Select::make('cols')
                                                    ->label('Колонок')
                                                    ->options(['2' => '2', '3' => '3', '4' => '4'])
                                                    ->default('3'),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Карточки')
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('icon')
                                                            ->label('Иконка (эмодзи)')
                                                            ->maxLength(10),
                                                        Forms\Components\TextInput::make('title')
                                                            ->label('Заголовок')
                                                            ->required()
                                                            ->maxLength(120),
                                                        Forms\Components\Textarea::make('text')
                                                            ->label('Описание')
                                                            ->rows(2)
                                                            ->columnSpanFull(),
                                                        Forms\Components\TextInput::make('link')
                                                            ->label('Ссылка')
                                                            ->url()
                                                            ->maxLength(255),
                                                    ])
                                                    ->columns(2)
                                                    ->minItems(1),
                                            ])
                                            ->columns(2),

                                        Block::make('faq')
                                            ->label('FAQ / Аккордеон')
                                            ->icon('heroicon-o-question-mark-circle')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Вопрос–ответ')
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('question')
                                                            ->label('Вопрос')
                                                            ->required()
                                                            ->maxLength(300)
                                                            ->columnSpanFull(),
                                                        Forms\Components\Textarea::make('answer')
                                                            ->label('Ответ')
                                                            ->rows(3)
                                                            ->required()
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->minItems(1),
                                            ]),

                                        Block::make('separator')
                                            ->label('Разделитель')
                                            ->icon('heroicon-o-minus')
                                            ->schema([
                                                Forms\Components\Select::make('style')
                                                    ->label('Стиль')
                                                    ->options(['line' => 'Линия', 'space' => 'Отступ', 'dots' => '· · ·'])
                                                    ->default('line'),
                                                Forms\Components\Select::make('size')
                                                    ->label('Размер')
                                                    ->options(['sm' => 'Маленький', 'md' => 'Средний', 'lg' => 'Большой'])
                                                    ->default('md'),
                                            ])
                                            ->columns(2),

                                    ])
                                    ->addActionLabel('+ Добавить блок'),
                            ])
                            ->columnSpanFull(),

                        Tab::make('Устаревший контент')
                            ->schema([
                                Forms\Components\Placeholder::make('legacy_hint')
                                    ->label('')
                                    ->content('Используется для материалов до внедрения конструктора. Если на вкладке «Конструктор» есть блоки — этот контент игнорируется.')
                                    ->columnSpanFull(),
                                TiptapEditor::make('content')
                                    ->label('')
                                    ->profile('default')
                                    ->directory('news-content')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('SEO title')
                                    ->maxLength(255)
                                    ->helperText('Если пусто — используется заголовок.'),
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
