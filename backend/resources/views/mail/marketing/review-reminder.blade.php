<x-mail::message>
# Здравствуйте, {{ $user->name ?: 'покупатель' }}!

Надеемся, заказ **{{ $order->order_number }}** вам понравился. Поделитесь впечатлением — отзыв помогает другим покупателям.

@php
    $names = $order->items->pluck('product_name')->take(4)->implode(', ');
@endphp

@if ($names !== '')
Товары в заказе: {{ $names }}@if ($order->items->count() > 4) и другие@endif.
@endif

@php
    $shopUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
@endphp
<x-mail::button :url="$shopUrl">
Открыть магазин
</x-mail::button>

Спасибо за покупку!<br>
{{ config('app.name') }}
</x-mail::message>
