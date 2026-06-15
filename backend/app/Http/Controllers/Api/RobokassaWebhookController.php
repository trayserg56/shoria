<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookLog;
use App\Support\Payments\Gateways\RobokassaPaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RobokassaWebhookController extends Controller
{
    /**
     * ResultURL — Робокасса вызывает при успешной оплате.
     * Ответ обязан быть plain-text "OK{InvId}".
     */
    public function result(Request $request): Response
    {
        $outSum        = $request->input('OutSum', '');
        $invId         = (int) $request->input('InvId', 0);
        $signatureValue = $request->input('SignatureValue', '');

        try {
            $provider = PaymentProvider::query()
                ->where('driver', 'robokassa')
                ->where('is_active', true)
                ->first();

            if (! $provider) {
                Log::warning('Robokassa webhook: provider not found or inactive');

                return response('PROVIDER_ERROR', 500);
            }

            $password2 = data_get($provider->config, 'password2', config('services.robokassa.password2'));

            if (! RobokassaPaymentGateway::verifyWebhookSignature($outSum, $invId, $signatureValue, $password2)) {
                Log::warning('Robokassa webhook: invalid signature', compact('outSum', 'invId'));

                return response('BAD_SIGN', 400);
            }

            DB::transaction(function () use ($provider, $outSum, $invId, $signatureValue, $request) {
                $order = Order::query()->find($invId);

                $transaction = $order
                    ? PaymentTransaction::query()
                        ->where('order_id', $order->id)
                        ->where('provider', $provider->code)
                        ->latest('id')
                        ->first()
                    : null;

                PaymentWebhookLog::query()->create([
                    'order_id'              => $order?->id,
                    'payment_transaction_id' => $transaction?->id,
                    'provider_code'         => $provider->code,
                    'external_event_id'     => null,
                    'provider_payment_id'   => (string) $invId,
                    'order_number'          => $order?->order_number,
                    'event_type'            => 'paid',
                    'status'                => 'processed',
                    'payload'               => $request->all(),
                    'result'                => ['inv_id' => $invId, 'out_sum' => $outSum],
                    'processed_at'          => now(),
                ]);

                if (! $order || ! $transaction) {
                    return;
                }

                if ($transaction->status !== 'succeeded') {
                    $transaction->status       = 'succeeded';
                    $transaction->confirmed_at = now();
                    $transaction->save();
                }

                if ($order->payment_status !== 'paid') {
                    $order->payment_status = 'paid';
                    if ($order->order_status === 'placed') {
                        $order->order_status = 'confirmed';
                    }
                    $order->save();
                }
            });

            return response("OK{$invId}");

        } catch (Throwable $e) {
            Log::error('Robokassa webhook error: ' . $e->getMessage(), ['exception' => $e]);

            return response('INTERNAL_ERROR', 500);
        }
    }

    /**
     * SuccessURL — браузер покупателя редиректится сюда после оплаты.
     * Переадресуем на страницу заказа в SPA.
     */
    public function success(Request $request): \Illuminate\Http\RedirectResponse
    {
        $invId = (int) $request->input('InvId', 0);

        $order = Order::query()->find($invId);

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if ($order) {
            return redirect("{$frontendUrl}/order-success/{$order->order_number}");
        }

        return redirect("{$frontendUrl}/");
    }

    /**
     * FailURL — браузер покупателя попадает сюда при отмене или ошибке оплаты.
     */
    public function fail(Request $request): \Illuminate\Http\RedirectResponse
    {
        $invId = (int) $request->input('InvId', 0);

        $order = Order::query()->find($invId);

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if ($order) {
            return redirect("{$frontendUrl}/order-success/{$order->order_number}?payment=failed");
        }

        return redirect("{$frontendUrl}/cart");
    }
}
