<x-mail::message>
# Здравствуйте, {{ $user->name ?: 'покупатель' }}!

В корзине остались товары. Вы можете продолжить оформление в любое удобное время.

@php
    $lines = $cart->items->take(5)->map(fn ($i) => '• '.$i->product_name.' — '.$i->qty.' шт.')->implode("\n");
@endphp

<x-mail::panel>
{{ $lines }}
@if ($cart->items->count() > 5)
… и ещё позиции
@endif
</x-mail::panel>

@php
    $cartUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/').'/cart';
@endphp
<x-mail::button :url="$cartUrl">
Перейти в корзину
</x-mail::button>

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
