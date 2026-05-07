<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCertificateAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $query = GiftCertificate::query()
            ->where('owner_user_id', $user->id);

        if (! $request->boolean('include_used')) {
            $query->usableForCustomer();
        }

        $items = $query
            ->orderByDesc('id')
            ->get()
            ->map(fn (GiftCertificate $certificate): array => [
                'id' => $certificate->id,
                'code' => $certificate->code,
                'balance_remaining' => (float) $certificate->balance_remaining,
                'initial_amount' => (float) $certificate->initial_amount,
                'currency' => $certificate->currency,
                'status' => $certificate->status,
                'expires_at' => $certificate->expires_at,
                'created_at' => $certificate->created_at,
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }
}
