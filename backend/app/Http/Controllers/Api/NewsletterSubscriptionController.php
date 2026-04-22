<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $source = $validated['source'] ?? 'home';

        $subscription = NewsletterSubscription::query()->where('email', $email)->first();

        if ($subscription) {
            $subscription->update([
                'source' => $source,
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'ok' => true,
                'status' => 'already_subscribed',
                'message' => 'Email уже подписан. Мы обновили параметры подписки.',
            ]);
        }

        NewsletterSubscription::query()->create([
            'email' => $email,
            'source' => $source,
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'ok' => true,
            'status' => 'subscribed',
            'message' => 'Подписка оформлена.',
        ], 201);
    }
}
