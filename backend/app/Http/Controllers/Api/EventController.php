<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:64'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        TrackingEvent::query()->create([
            'event_name' => $validated['event_name'],
            'page_url' => $validated['page_url'] ?? $request->header('Origin'),
            'referrer' => $validated['referrer'] ?? $request->header('Referer'),
            'session_id' => $validated['session_id'] ?? null,
            'payload' => $validated['payload'] ?? [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => isset($validated['occurred_at'])
                ? Carbon::parse($validated['occurred_at'])
                : now(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
