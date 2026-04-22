<?php

namespace App\Filament\Widgets;

use App\Support\Analytics\MarketingDashboardData;
use Filament\Widgets\ChartWidget;

class MarketingTrendsChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Динамика спроса и конверсии';

    protected ?string $description = 'Последние 14 дней: просмотры, добавления в корзину, покупки и email-подписки.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $daily = app(MarketingDashboardData::class)->forLastDays(14)['daily'];

        return [
            'datasets' => [
                [
                    'label' => 'Просмотры товара',
                    'data' => $daily['views'],
                    'borderColor' => '#1f2233',
                    'backgroundColor' => 'rgba(31, 34, 51, 0.08)',
                    'tension' => 0.35,
                ],
                [
                    'label' => 'В корзину',
                    'data' => $daily['add_to_cart'],
                    'borderColor' => '#f35b04',
                    'backgroundColor' => 'rgba(243, 91, 4, 0.08)',
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Покупки',
                    'data' => $daily['purchase'],
                    'borderColor' => '#14b8a6',
                    'backgroundColor' => 'rgba(20, 184, 166, 0.08)',
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Подписки',
                    'data' => $daily['newsletter_subscriptions'],
                    'borderColor' => '#7c3aed',
                    'backgroundColor' => 'rgba(124, 58, 237, 0.08)',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $daily['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
