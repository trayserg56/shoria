<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Payments\PaymentWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentWebhookProcessor $processor)
    {
    }

    public function store(Request $request, string $providerCode): JsonResponse
    {
        try {
            $log = $this->processor->process($providerCode, $request->all());

            return response()->json([
                'ok' => true,
                'status' => $log->status,
                'provider_code' => $providerCode,
                'log_id' => $log->id,
                'result' => $log->result,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'status' => 'failed',
                'provider_code' => $providerCode,
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }
}
