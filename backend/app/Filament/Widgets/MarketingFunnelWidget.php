<?php

namespace App\Filament\Widgets;

use App\Support\Analytics\MarketingDashboardData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingFunnelWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Маркетинговая воронка';

    protected ?string $description = 'Считаем уникальные session_id по ключевым событиям за последние 30 дней.';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $funnel = app(MarketingDashboardData::class)->forLastDays(30)['funnel'];

        return [
            Stat::make('Просмотр товара', $this->formatInteger($funnel['view_product_sessions']))
                ->description('Старт воронки')
                ->descriptionIcon('heroicon-m-eye'),
            Stat::make('Добавление в корзину', $this->formatInteger($funnel['add_to_cart_sessions']))
                ->description($this->formatRate($funnel['view_to_cart_rate'], 'от просмотров'))
                ->descriptionIcon('heroicon-m-shopping-cart'),
            Stat::make('Начало checkout', $this->formatInteger($funnel['begin_checkout_sessions']))
                ->description($this->formatRate($funnel['cart_to_checkout_rate'], 'от корзины'))
                ->descriptionIcon('heroicon-m-credit-card'),
            Stat::make('Покупка', $this->formatInteger($funnel['purchase_sessions']))
                ->description($this->formatRate($funnel['checkout_to_purchase_rate'], 'от checkout') . ' · ' . $this->formatRate($funnel['view_to_purchase_rate'], 'от просмотров'))
                ->descriptionIcon('heroicon-m-trophy'),
        ];
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    private function formatRate(float $value, string $suffix): string
    {
        return number_format($value, 1, ',', ' ') . '% ' . $suffix;
    }
}
