<?php

namespace App\Filament\Resources\ServicePages\Schemas;

use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
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

                        Tab::make('Конструктор блоков')
                            ->schema([
                                Forms\Components\Placeholder::make('blocks_hint')
                                    ->label('')
                                    ->content('Добавляйте блоки в нужном порядке. Перетаскивайте для сортировки. Если блоки заданы — они заменят текст из вкладки «Устаревший контент».')
                                    ->columnSpanFull(),

                                Builder::make('blocks')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable()
                                    ->blocks([

                                        // ── Текст ──────────────────────────────────────────
                                        Block::make('text')
                                            ->label('Текст')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                TiptapEditor::make('content')
                                                    ->label('Контент')
                                                    ->profile('default')
                                                    ->directory('service-pages')
                                                    ->columnSpanFull()
                                                    ->required(),
                                            ]),

                                        // ── Выноска (callout) ───────────────────────────────
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

                                        // ── Список с галочками (checklist) ──────────────────
                                        Block::make('checklist')
                                            ->label('Список преимуществ')
                                            ->icon('heroicon-o-check-badge')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок раздела (необязательно)')
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
                                                            ->label('Уточнение (мелкий текст)')
                                                            ->maxLength(300),
                                                    ])
                                                    ->columns(2)
                                                    ->minItems(1),
                                            ]),

                                        // ── Шаги (steps) ────────────────────────────────────
                                        Block::make('steps')
                                            ->label('Пошаговая инструкция')
                                            ->icon('heroicon-o-list-bullet')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок раздела (необязательно)')
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
                                                    ->columns(1)
                                                    ->minItems(1),
                                            ]),

                                        // ── Картинка (image) ─────────────────────────────────
                                        Block::make('image')
                                            ->label('Изображение')
                                            ->icon('heroicon-o-photo')
                                            ->schema([
                                                Forms\Components\TextInput::make('src')
                                                    ->label('URL изображения')
                                                    ->url()
                                                    ->required()
                                                    ->columnSpanFull()
                                                    ->helperText('Вставьте прямой URL изображения.'),
                                                Forms\Components\TextInput::make('alt')
                                                    ->label('Альтернативный текст (alt)')
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('caption')
                                                    ->label('Подпись под картинкой')
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('link')
                                                    ->label('Ссылка (необязательно)')
                                                    ->url()
                                                    ->maxLength(255),
                                                Forms\Components\Select::make('size')
                                                    ->label('Ширина')
                                                    ->options([
                                                        'full'   => 'Полная ширина',
                                                        'wide'   => 'Широкая (80%)',
                                                        'medium' => 'Средняя (60%)',
                                                        'small'  => 'Маленькая (40%)',
                                                    ])
                                                    ->default('full'),
                                            ])
                                            ->columns(2),

                                        // ── Призыв к действию (cta) ─────────────────────────
                                        Block::make('cta')
                                            ->label('Призыв к действию (кнопки)')
                                            ->icon('heroicon-o-cursor-arrow-rays')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок')
                                                    ->maxLength(200)
                                                    ->columnSpanFull(),
                                                Forms\Components\Textarea::make('text')
                                                    ->label('Текст под заголовком')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('buttons')
                                                    ->label('Кнопки')
                                                    ->columnSpanFull()
                                                    ->reorderable()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('label')
                                                            ->label('Текст кнопки')
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

                                        // ── Карточки в сетке (columns) ───────────────────────
                                        Block::make('columns')
                                            ->label('Карточки в сетке')
                                            ->icon('heroicon-o-squares-2x2')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок раздела (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Select::make('cols')
                                                    ->label('Количество колонок')
                                                    ->options([
                                                        '2' => '2 колонки',
                                                        '3' => '3 колонки',
                                                        '4' => '4 колонки',
                                                    ])
                                                    ->default('3'),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Карточки')
                                                    ->columnSpanFull()
                                                    ->reorderable()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('icon')
                                                            ->label('Иконка (эмодзи)')
                                                            ->maxLength(10)
                                                            ->placeholder('📦'),
                                                        Forms\Components\TextInput::make('title')
                                                            ->label('Заголовок карточки')
                                                            ->required()
                                                            ->maxLength(120),
                                                        Forms\Components\Textarea::make('text')
                                                            ->label('Описание')
                                                            ->rows(2)
                                                            ->columnSpanFull(),
                                                        Forms\Components\TextInput::make('link')
                                                            ->label('Ссылка (необязательно)')
                                                            ->url()
                                                            ->maxLength(255),
                                                    ])
                                                    ->columns(2)
                                                    ->minItems(1),
                                            ])
                                            ->columns(2),

                                        // ── FAQ / Аккордеон ──────────────────────────────────
                                        Block::make('faq')
                                            ->label('FAQ / Аккордеон')
                                            ->icon('heroicon-o-question-mark-circle')
                                            ->schema([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Заголовок раздела (необязательно)')
                                                    ->maxLength(160)
                                                    ->columnSpanFull(),
                                                Forms\Components\Repeater::make('items')
                                                    ->label('Вопросы и ответы')
                                                    ->columnSpanFull()
                                                    ->reorderable()
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

                                        // ── Разделитель ──────────────────────────────────────
                                        Block::make('separator')
                                            ->label('Разделитель / отступ')
                                            ->icon('heroicon-o-minus')
                                            ->schema([
                                                Forms\Components\Select::make('style')
                                                    ->label('Стиль')
                                                    ->options([
                                                        'line'  => 'Горизонтальная линия',
                                                        'space' => 'Просто отступ',
                                                        'dots'  => 'Точки (· · ·)',
                                                    ])
                                                    ->default('line'),
                                                Forms\Components\Select::make('size')
                                                    ->label('Размер отступа')
                                                    ->options([
                                                        'sm' => 'Маленький',
                                                        'md' => 'Средний',
                                                        'lg' => 'Большой',
                                                    ])
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
                                    ->content('Этот редактор используется для страниц, созданных до появления конструктора блоков. Если на вкладке «Конструктор» есть хотя бы один блок — этот контент игнорируется.')
                                    ->columnSpanFull(),
                                TiptapEditor::make('content')
                                    ->label('')
                                    ->profile('default')
                                    ->directory('service-pages')
                                    ->columnSpanFull(),
                            ]),

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
