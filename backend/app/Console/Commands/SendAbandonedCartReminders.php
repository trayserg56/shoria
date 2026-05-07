<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'marketing:send-abandoned-cart-reminders';

    protected $description = 'Отправить напоминания об открытой корзине (авторизованные покупатели)';

    public function handle(): int
    {
        $hours = max(1, (int) config('marketing.abandoned_cart_after_hours', 24));

        $query = Cart::query()
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->whereNull('abandoned_cart_reminded_at')
            ->where('updated_at', '<=', now()->subHours($hours))
            ->whereHas('items');

        $sent = 0;

        $query->with(['user', 'items'])->chunkById(50, function ($carts) use (&$sent): void {
            foreach ($carts as $cart) {
                $user = $cart->user;

                if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                Mail::to($user->email)->send(new AbandonedCartMail($cart, $user));

                Cart::query()->whereKey($cart->id)->update([
                    'abandoned_cart_reminded_at' => now(),
                ]);

                $sent++;
            }
        });

        $this->info("Отправлено писем об отложенной корзине: {$sent}");

        return self::SUCCESS;
    }
}
