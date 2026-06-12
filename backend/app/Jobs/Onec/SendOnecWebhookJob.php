<?php

namespace App\Jobs\Onec;

use App\Models\OnecWebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOnecWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly string $event,
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        $url = config('services.onec.webhook_url');

        if (! $url) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE);
        $secret = config('services.onec.webhook_secret', '');
        $signature = hash_hmac('sha256', $body, $secret);

        $log = OnecWebhookLog::create([
            'event' => $this->event,
            'payload' => json_encode($this->payload, JSON_UNESCAPED_UNICODE),
            'attempts' => $this->attempts(),
            'status' => 'pending',
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-1C-Event' => $this->event,
                'X-1C-Signature' => $signature,
            ])
                ->timeout(15)
                ->post($url, $this->payload);

            $log->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'response' => "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 900),
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("Webhook вернул HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'response' => $e->getMessage()]);

            Log::warning('OnecWebhook: ошибка отправки', [
                'event' => $this->event,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('OnecWebhook: все попытки исчерпаны', [
            'event' => $this->event,
            'error' => $e->getMessage(),
        ]);
    }
}
