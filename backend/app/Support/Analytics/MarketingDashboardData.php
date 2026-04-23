<?php

namespace App\Support\Analytics;

use App\Models\NewsletterSubscription;
use App\Models\Order;
use App\Models\TrackingEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MarketingDashboardData
{
    public function forLastDays(int $days = 30): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(max(0, $days - 1))->startOfDay();

        return $this->forPeriod($start, $end);
    }

    public function forPeriod(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $events = TrackingEvent::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->get(['event_name', 'session_id', 'occurred_at', 'first_touch_source']);

        $orders = Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->get(['id', 'order_number', 'placed_at', 'total', 'first_touch_source']);

        $subscriptions = NewsletterSubscription::query()
            ->where('status', 'subscribed')
            ->whereBetween('subscribed_at', [$start, $end])
            ->get(['id', 'subscribed_at']);

        $viewSessions = $this->uniqueSessionsForEvent($events, 'view_product');
        $cartSessions = $this->uniqueSessionsForEvent($events, 'add_to_cart');
        $checkoutSessions = $this->uniqueSessionsForEvent($events, 'begin_checkout');
        $purchaseSessions = $this->uniqueSessionsForEvent($events, 'purchase');

        $ordersCount = $orders->count();
        $revenue = (float) $orders->sum(fn (Order $order) => (float) $order->total);

        return [
            'period' => [
                'start' => $start,
                'end' => $end,
                'label' => sprintf('%s - %s', $start->format('d.m.Y'), $end->format('d.m.Y')),
            ],
            'overview' => [
                'sessions' => $events->pluck('session_id')->filter()->unique()->count(),
                'orders' => $ordersCount,
                'revenue' => $revenue,
                'average_order_value' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.0,
                'newsletter_subscriptions' => $subscriptions->count(),
                'purchase_sessions' => $purchaseSessions,
            ],
            'funnel' => [
                'view_product_sessions' => $viewSessions,
                'add_to_cart_sessions' => $cartSessions,
                'begin_checkout_sessions' => $checkoutSessions,
                'purchase_sessions' => $purchaseSessions,
                'view_to_cart_rate' => $this->rate($cartSessions, $viewSessions),
                'cart_to_checkout_rate' => $this->rate($checkoutSessions, $cartSessions),
                'checkout_to_purchase_rate' => $this->rate($purchaseSessions, $checkoutSessions),
                'view_to_purchase_rate' => $this->rate($purchaseSessions, $viewSessions),
            ],
            'attribution' => $this->buildAttributionBreakdown($events, $orders),
            'daily' => $this->buildDailySeries($start, $end, $events, $orders, $subscriptions),
        ];
    }

    private function buildAttributionBreakdown(Collection $events, Collection $orders): array
    {
        $sessionGroups = $events
            ->filter(fn (TrackingEvent $event) => filled($event->session_id))
            ->groupBy(fn (TrackingEvent $event) => $event->first_touch_source ?: 'direct')
            ->map(fn (Collection $group) => $group->pluck('session_id')->unique()->count());

        $orderGroups = $orders
            ->groupBy(fn (Order $order) => $order->first_touch_source ?: 'direct');

        $topSources = $sessionGroups
            ->keys()
            ->merge($orderGroups->keys())
            ->unique()
            ->values()
            ->map(fn (string $source) => [
                'label' => $source,
                'sessions' => (int) ($sessionGroups[$source] ?? 0),
                'orders' => $orderGroups->get($source, collect())->count(),
                'revenue' => round((float) $orderGroups->get($source, collect())->sum(fn (Order $order) => (float) $order->total), 2),
            ])
            ->sortByDesc(fn (array $row) => [$row['sessions'], $row['orders'], $row['revenue']])
            ->take(6)
            ->values();

        return [
            'labels' => $topSources->pluck('label')->all(),
            'sessions' => $topSources->pluck('sessions')->all(),
            'orders' => $topSources->pluck('orders')->all(),
            'revenue' => $topSources->pluck('revenue')->all(),
        ];
    }

    private function buildDailySeries(
        CarbonImmutable $start,
        CarbonImmutable $end,
        Collection $events,
        Collection $orders,
        Collection $subscriptions,
    ): array {
        $labels = [];
        $views = [];
        $addsToCart = [];
        $checkouts = [];
        $purchases = [];
        $newsletter = [];
        $revenue = [];

        for ($cursor = $start->startOfDay(); $cursor->lte($end); $cursor = $cursor->addDay()) {
            $nextDay = $cursor->addDay();
            $labels[] = $cursor->format('d.m');

            $views[] = $this->dailyUniqueSessionCount($events, 'view_product', $cursor, $nextDay);
            $addsToCart[] = $this->dailyUniqueSessionCount($events, 'add_to_cart', $cursor, $nextDay);
            $checkouts[] = $this->dailyUniqueSessionCount($events, 'begin_checkout', $cursor, $nextDay);
            $purchases[] = $this->dailyUniqueSessionCount($events, 'purchase', $cursor, $nextDay);
            $newsletter[] = $subscriptions
                ->filter(fn (NewsletterSubscription $subscription) => $subscription->subscribed_at?->between($cursor, $nextDay, false))
                ->count();
            $revenue[] = round((float) $orders
                ->filter(fn (Order $order) => $order->placed_at?->between($cursor, $nextDay, false))
                ->sum(fn (Order $order) => (float) $order->total), 2);
        }

        return [
            'labels' => $labels,
            'views' => $views,
            'add_to_cart' => $addsToCart,
            'begin_checkout' => $checkouts,
            'purchase' => $purchases,
            'newsletter_subscriptions' => $newsletter,
            'revenue' => $revenue,
        ];
    }

    private function dailyUniqueSessionCount(
        Collection $events,
        string $eventName,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): int {
        return $events
            ->filter(
                fn (TrackingEvent $event) => $event->event_name === $eventName
                    && $event->occurred_at?->between($from, $to, false)
                    && filled($event->session_id),
            )
            ->pluck('session_id')
            ->unique()
            ->count();
    }

    private function uniqueSessionsForEvent(Collection $events, string $eventName): int
    {
        return $events
            ->where('event_name', $eventName)
            ->pluck('session_id')
            ->filter()
            ->unique()
            ->count();
    }

    private function rate(int $value, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(($value / $base) * 100, 1);
    }
}
