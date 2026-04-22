<?php

namespace App\Filament\Widgets;

use App\Support\Analytics\MarketingDashboardData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $metrics = app(MarketingDashboardData::class)->forLastDays(30);
        $overview = $metrics['overview'];
        $periodLabel = 'За последние 30 дней';

        return [
            Stat::make('Сессии', $this->formatInteger($overview['sessions']))
                ->description($periodLabel)
                ->descriptionIcon('heroicon-m-cursor-arrow-rays'),
            Stat::make('Заказы', $this->formatInteger($overview['orders']))
                ->description('Оформлено заказов')
                ->descriptionIcon('heroicon-m-shopping-bag'),
            Stat::make('Выручка', $this->formatCurrency($overview['revenue']))
                ->description('Фактическая сумма заказов')
                ->descriptionIcon('heroicon-m-banknotes'),
            Stat::make('Средний чек', $this->formatCurrency($overview['average_order_value']))
                ->description('Выручка / количество заказов')
                ->descriptionIcon('heroicon-m-calculator'),
            Stat::make('Подписки', $this->formatInteger($overview['newsletter_subscriptions']))
                ->description('Новые email-подписки')
                ->descriptionIcon('heroicon-m-envelope'),
            Stat::make('Покупки', $this->formatInteger($overview['purchase_sessions']))
                ->description('Уникальные сессии с purchase')
                ->descriptionIcon('heroicon-m-check-badge'),
        ];
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    private function formatCurrency(float $value): string
    {
        return number_format($value, 0, ',', ' ') . ' ₽';
    }
}
