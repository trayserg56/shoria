<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\SiteSetting;
use App\Support\Admin\AdminAccess;
use App\Support\Store\StoreFeedSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class YandexFeedPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?string $navigationLabel = 'YML-фид Яндекс.Маркет';

    protected static ?string $title = 'YML-фид для Яндекс.Маркет';

    protected static string|UnitEnum|null $navigationGroup = 'Маркетинг';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'yandex-feed';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAccess::canManageContentResource('yandex_feed');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = SiteSetting::current()->mergedFeedSettings();

        // Если included_category_ids не задан (null) — считаем что все выбраны
        if (! isset($settings['included_category_ids']) || $settings['included_category_ids'] === null) {
            $settings['included_category_ids'] = Category::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $settings['included_category_ids'] = array_map('strval', $settings['included_category_ids']);
        }

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        $categories = Category::query()
            ->select(['id', 'name', 'parent_id'])
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        $categoryOptions = $categories
            ->mapWithKeys(function ($cat) {
                $prefix = $cat->parent_id ? '↳ ' : '';
                return [(string) $cat->id => $prefix.$cat->name];
            })
            ->all();

        $appUrl = rtrim((string) config('app.url', ''), '/');
        $feedUrl = $appUrl.'/feed/yandex.xml';
        $cacheStatus = Cache::has('shoria:feed:yandex:yml') ? '🟢 Кэш активен' : '🟡 Кэш пуст (перестроится при первом запросе)';

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Статус фида')
                    ->schema([
                        Forms\Components\Placeholder::make('feed_status')
                            ->label('Состояние кэша')
                            ->content($cacheStatus),
                        Forms\Components\Placeholder::make('feed_url')
                            ->label('URL фида для Яндекс.Маркет')
                            ->content($feedUrl),
                        Forms\Components\Placeholder::make('feed_instruction')
                            ->label('Инструкция')
                            ->content('Перейдите на partner.market.yandex.ru → Добавить магазин → Прайс-лист (YML) → вставьте URL выше.'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Основное')
                    ->description('Название магазина и компании в фиде')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('shop_name')
                                ->label('Название магазина')
                                ->placeholder(config('app.name', 'Shoria'))
                                ->helperText('Если пусто — берётся название приложения из конфига')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('company_name')
                                ->label('Название компании (юр.лицо)')
                                ->placeholder('ООО «Шория»')
                                ->maxLength(255),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\Select::make('currency')
                                ->label('Валюта')
                                ->options(['RUB' => 'RUB — Российский рубль'])
                                ->default('RUB')
                                ->required(),

                            Forms\Components\TextInput::make('sales_notes')
                                ->label('Условия продажи (sales_notes)')
                                ->placeholder('Доставка по всей России')
                                ->maxLength(50)
                                ->helperText('До 50 символов. Показывается покупателю на Маркете'),
                        ]),
                    ]),

                Section::make('Фильтрация товаров')
                    ->description('Управляйте тем, какие товары попадают в фид')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('min_price')
                                ->label('Минимальная цена (₽)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->helperText('Товары дешевле указанной цены не попадут в фид. 0 = без ограничения'),

                            Forms\Components\Select::make('max_images')
                                ->label('Макс. кол-во фото на товар')
                                ->options(array_combine(range(1, 10), range(1, 10)))
                                ->default(10)
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\Toggle::make('include_out_of_stock')
                                ->label('Включать товары без наличия')
                                ->helperText('Если выключено — товары с остатком 0 не попадут в фид')
                                ->default(false),

                            Forms\Components\Toggle::make('enable_oldprice')
                                ->label('Показывать старую цену')
                                ->helperText('Зачёркнутая цена на Маркете (если есть скидка)')
                                ->default(true),
                        ]),
                    ]),

                Section::make('Категории')
                    ->description('Снимите галочку с категории — она и все её подкатегории не попадут в фид')
                    ->schema([
                        Forms\Components\CheckboxList::make('included_category_ids')
                            ->label('')
                            ->options($categoryOptions)
                            ->columns(3)
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('yandex-feed-form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save_form')
                            ->label('Сохранить настройки')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $allCategoryIds = Category::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $checked = array_map('intval', $data['included_category_ids'] ?? []);
            // Если выбраны все — храним null (= без ограничений)
            sort($checked);
            sort($allCategoryIds);
            $data['included_category_ids'] = $checked === $allCategoryIds ? null : array_values($checked);

            $settings = SiteSetting::current();
            $settings->forceFill(['feed_settings' => $data])->save();

            // Сбросить кэш фида — настройки изменились
            Cache::forget('shoria:feed:yandex:yml');

            Notification::make()
                ->title('Настройки фида сохранены')
                ->success()
                ->send();
        } catch (Halt) {
            // пользователь нажал отмену в confirmation modal
        }
    }

    protected function getHeaderActions(): array
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $feedUrl = $appUrl.'/feed/yandex.xml';

        return [
            Action::make('open_feed')
                ->label('Открыть фид')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url($feedUrl, shouldOpenInNewTab: true),

            Action::make('refresh_feed')
                ->label('Сбросить кэш')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Сбросить кэш фида?')
                ->modalDescription('Фид будет перестроен из БД при следующем запросе.')
                ->action(function () {
                    Cache::forget('shoria:feed:yandex:yml');
                    Notification::make()
                        ->title('Кэш фида сброшен')
                        ->success()
                        ->send();
                }),

            Action::make('save')
                ->label('Сохранить настройки')
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
        ];
    }
}
