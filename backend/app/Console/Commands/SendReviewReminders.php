<?php

namespace App\Console\Commands;

use App\Mail\ReviewReminderMail;
use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReviewReminders extends Command
{
    protected $signature = 'marketing:send-review-reminders';

    protected $description = 'Напоминание об отзыве после доставки заказа';

    public function handle(): int
    {
        $days = max(0, (int) config('marketing.review_reminder_after_delivered_days', 3));
        $threshold = now()->subDays($days);

        $sent = 0;

        Order::query()
            ->whereNotNull('user_id')
            ->whereNull('marketing_review_reminder_sent_at')
            ->where('order_status', '!=', 'cancelled')
            ->where('fulfillment_status', 'delivered')
            ->where('updated_at', '<=', $threshold)
            ->with(['user', 'items'])
            ->chunkById(50, function ($orders) use (&$sent): void {
                foreach ($orders as $order) {
                    $user = $order->user;

                    if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        Order::query()->whereKey($order->id)->update([
                            'marketing_review_reminder_sent_at' => now(),
                        ]);

                        continue;
                    }

                    $pendingItems = $order->items->filter(
                        fn ($item) => ! ProductReview::query()
                            ->where('order_item_id', $item->id)
                            ->exists(),
                    );

                    if ($pendingItems->isEmpty()) {
                        Order::query()->whereKey($order->id)->update([
                            'marketing_review_reminder_sent_at' => now(),
                        ]);

                        continue;
                    }

                    Mail::to($user->email)->send(new ReviewReminderMail($order, $user));

                    Order::query()->whereKey($order->id)->update([
                        'marketing_review_reminder_sent_at' => now(),
                    ]);

                    $sent++;
                }
            });

        $this->info("Отправлено напоминаний об отзыве: {$sent}");

        return self::SUCCESS;
    }
}
