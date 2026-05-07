<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingEventStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_endpoint_accepts_view_product_payload(): void
    {
        $response = $this->postJson('/api/events', [
            'event_name' => 'view_product',
            'page_url' => 'http://127.0.0.1:8080/product/lifestyle/city-frame-one',
            'referrer' => null,
            'session_id' => 'test-session-1',
            'occurred_at' => now()->toIso8601String(),
            'attribution' => [
                'source' => 'direct',
                'medium' => 'direct',
                'campaign' => null,
                'content' => null,
                'term' => null,
                'landing_path' => '/product/lifestyle/city-frame-one',
                'referrer_host' => null,
            ],
            'payload' => ['slug' => 'city-frame-one'],
        ]);

        $response->assertCreated();
        $response->assertJson(['ok' => true]);
        $this->assertDatabaseHas('tracking_events', [
            'event_name' => 'view_product',
            'session_id' => 'test-session-1',
        ]);
    }
}
