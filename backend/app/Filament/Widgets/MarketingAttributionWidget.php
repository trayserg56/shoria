<?php

namespace App\Filament\Widgets;

use App\Support\Analytics\MarketingDashboardData;
use Filament\Widgets\ChartWidget;

class MarketingAttributionWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Источники трафика и выручка';

    protected ?string $description = 'Топ first-touch источников за последние 30 дней: сессии, заказы и выручка.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $attribution = app(MarketingDashboardData::class)->forLastDays(30)['attribution'];

        return [
            'datasets' => [
                [
                    'label' => 'Сессии',
                    'data' => $attribution['sessions'],
                    'backgroundColor' => '#1f2233',
                ],
                [
                    'label' => 'Заказы',
                    'data' => $attribution['orders'],
                    'backgroundColor' => '#f35b04',
                ],
                [
                    'label' => 'Выручка, ₽',
                    'data' => $attribution['revenue'],
                    'backgroundColor' => '#14b8a6',
                ],
            ],
            'labels' => $attribution['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
