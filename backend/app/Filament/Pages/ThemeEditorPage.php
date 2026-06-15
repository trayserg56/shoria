<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\Admin\AdminAccess;
use App\Support\Store\StoreTheme;
use BackedEnum;
use App\Filament\Forms\Components\VariantPicker;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ThemeEditorPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Редактор темы';

    protected static ?string $title = 'Редактор темы сайта';

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'theme-editor';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(StoreTheme::merge(SiteSetting::current()->theme));
    }

    public static function canAccess(): bool
    {
        return AdminAccess::canManageContentResource('site_settings');
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $homeFields = [];
        foreach (StoreTheme::homeSectionLabels() as $key => $label) {
            $homeFields[] = Forms\Components\Toggle::make('home.sections.'.$key.'.enabled')
                ->label($label)
                ->default(true);
        }

        return $schema
            ->components([
                Tabs::make('theme')
                    ->tabs([
                        Tab::make('Общие')
                            ->schema([
                                Section::make('Сетка и типографика')
                                    ->schema([
                                        Forms\Components\Select::make('general.container_width_px')
                                            ->label('Ширина контента')
                                            ->options([
                                                1296 => '1296 px',
                                                1464 => '1464 px',
                                                1500 => '1500 px',
                                                1696 => '1696 px',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Forms\Components\Select::make('general.body_font')
                                            ->label('Основной шрифт')
                                            ->options([
                                                'manrope' => 'Manrope',
                                                'rubik' => 'Rubik',
                                                'inter' => 'Inter',
                                                'system' => 'Системный',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Forms\Components\Select::make('general.display_font')
                                            ->label('Акцентный шрифт (логотип, крупные заголовки)')
                                            ->options([
                                                'bebas' => 'Bebas Neue',
                                                'manrope' => 'Manrope',
                                                'oswald' => 'Oswald',
                                                'system' => 'Системный',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Forms\Components\Select::make('general.base_font_size_px')
                                            ->label('Базовый кегль')
                                            ->options([
                                                15 => '15 px',
                                                16 => '16 px',
                                                17 => '17 px',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Forms\Components\Toggle::make('general.use_display_for_headings')
                                            ->label('Заголовки разделов тем же шрифтом, что и «акцентный»')
                                            ->default(true),
                                        Forms\Components\Select::make('general.heading_weight')
                                            ->label('Начертание заголовков')
                                            ->options([
                                                '700' => 'Жирный',
                                                '600' => 'Полужирный',
                                                '500' => 'Средний',
                                                '400' => 'Обычный',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Forms\Components\Select::make('general.button_radius_px')
                                            ->label('Скругление кнопок')
                                            ->options([
                                                0 => '0 px',
                                                6 => '6 px',
                                                8 => '8 px',
                                                10 => '10 px',
                                                12 => '12 px',
                                                16 => '16 px',
                                                20 => '20 px',
                                                24 => '24 px',
                                            ])
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->columns(2),
                                Section::make('Цвета акцента')
                                    ->description('Влияют на основные кнопки и элементы в стиле shadcn / Naive UI.')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('general.primary_hex')
                                            ->label('Основной цвет')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('general.primary_foreground_hex')
                                            ->label('Текст на основном фоне')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Шапка')
                            ->schema([
                                VariantPicker::make('header.variant')
                                    ->label('Вариант шапки (экраны от 1024 px)')
                                    ->variants([
                                        'classic' => [
                                            'label' => 'Классический',
                                            'description' => 'Лого и меню слева, поиск по центру, иконки справа',
                                            'preview' => 'header-classic',
                                        ],
                                        'centered' => [
                                            'label' => 'Лого по центру',
                                            'description' => 'Лого вверху по центру, поиск в нижней строке',
                                            'preview' => 'header-centered',
                                        ],
                                        'wide_search' => [
                                            'label' => 'Широкий поиск',
                                            'description' => 'Расширенная строка поиска по центру',
                                            'preview' => 'header-wide-search',
                                        ],
                                    ]),
                                Forms\Components\Toggle::make('header.sticky')
                                    ->label('Фиксировать шапку при прокрутке')
                                    ->default(true),
                            ]),
                        Tab::make('Подвал')
                            ->schema([
                                VariantPicker::make('footer.variant')
                                    ->label('Вариант футера')
                                    ->variants([
                                        'columns' => [
                                            'label' => 'Колонки',
                                            'description' => '4 колонки: бренд, меню, аккаунт, контакты',
                                            'preview' => 'footer-columns',
                                        ],
                                        'minimal' => [
                                            'label' => 'Минимальный',
                                            'description' => 'Одна строка: лого, ссылки и копирайт',
                                            'preview' => 'footer-minimal',
                                        ],
                                        'centered' => [
                                            'label' => 'Центрированный',
                                            'description' => 'Лого и ссылки по центру, копирайт снизу',
                                            'preview' => 'footer-centered',
                                        ],
                                    ]),
                                Forms\Components\Select::make('footer.tone')
                                    ->label('Тон подвала')
                                    ->options([
                                        'light' => 'Светлый',
                                        'muted' => 'Нейтральный',
                                        'dark' => 'Тёмный',
                                    ])
                                    ->required()
                                    ->native(false),
                            ]),
                        Tab::make('Главная')
                            ->schema([
                                Section::make('Главный баннер (слайдер)')
                                    ->schema([
                                        VariantPicker::make('hero.variant')
                                            ->label('Вариант слайдера')
                                            ->variants([
                                                'overlay' => [
                                                    'label' => 'Оверлей',
                                                    'description' => 'Текст поверх картинки с тёмным затемнением',
                                                    'preview' => 'hero-overlay',
                                                ],
                                                'split' => [
                                                    'label' => 'Сплит',
                                                    'description' => 'Текст слева на белом фоне, картинка справа',
                                                    'preview' => 'hero-split',
                                                ],
                                            ]),
                                    ]),
                                Section::make('Подборки и акции (баннеры)')
                                    ->schema([
                                        VariantPicker::make('marketing.variant')
                                            ->label('Вариант блока подборок')
                                            ->variants([
                                                'grid' => [
                                                    'label' => 'Сетка',
                                                    'description' => 'Равные карточки, 3 в ряд',
                                                    'preview' => 'marketing-grid',
                                                ],
                                                'mosaic' => [
                                                    'label' => 'Мозаика',
                                                    'description' => 'Первая карточка большая, остальные мелкие',
                                                    'preview' => 'marketing-mosaic',
                                                ],
                                                'list' => [
                                                    'label' => 'Список',
                                                    'description' => 'Горизонтальные карточки с текстом сбоку',
                                                    'preview' => 'marketing-list',
                                                ],
                                            ]),
                                    ]),
                                Section::make('Блоки страницы')
                                    ->description('Отключённые блоки не показываются на главной.')
                                    ->schema($homeFields)
                                    ->columns(2),
                            ]),
                        Tab::make('Каталог')
                            ->schema([
                                Forms\Components\Select::make('catalog.grid_density')
                                    ->label('Плотность сетки товаров')
                                    ->options([
                                        'comfortable' => 'Обычная',
                                        'compact' => 'Компактнее (меньше отступы)',
                                    ])
                                    ->required()
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('theme-form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(static::getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky(static::areFormActionsSticky())
                    ->key('theme-form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('reset')
                ->label('По умолчанию')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Сбросить тему?')
                ->modalDescription('Все значения темы будут возвращены к заводским.')
                ->action(function (): void {
                    $defaults = StoreTheme::defaults();
                    SiteSetting::current()->update(['theme' => $defaults]);
                    $this->form->fill($defaults);
                    Notification::make()
                        ->success()
                        ->title('Тема сброшена')
                        ->send();
                }),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $state = $this->form->getState();
            $merged = StoreTheme::merge($state);
            SiteSetting::current()->update(['theme' => $merged]);
            $this->form->fill($merged);

            Notification::make()
                ->success()
                ->title('Тема сохранена')
                ->send();
        } catch (Halt) {
            // Validation cancelled
        }
    }
}
