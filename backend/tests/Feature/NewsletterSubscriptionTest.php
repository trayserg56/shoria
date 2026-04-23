<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_subscribe_to_newsletter(): void
    {
        $response = $this->postJson('/api/newsletter/subscribe', [
            'email' => 'buyer@example.com',
            'source' => 'home',
            'attribution' => [
                'source' => 'telegram',
                'medium' => 'social',
                'campaign' => 'spring-drop',
                'landing_path' => '/?utm_source=telegram',
                'referrer_host' => 't.me',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'subscribed');

        $this->assertDatabaseHas('newsletter_subscriptions', [
            'email' => 'buyer@example.com',
            'source' => 'home',
            'status' => 'subscribed',
            'first_touch_source' => 'telegram',
            'first_touch_medium' => 'social',
            'first_touch_campaign' => 'spring-drop',
        ]);
    }

    public function test_subscribe_is_idempotent_for_same_email(): void
    {
        $this->postJson('/api/newsletter/subscribe', [
            'email' => 'repeat@example.com',
            'source' => 'home',
        ])->assertCreated();

        $secondResponse = $this->postJson('/api/newsletter/subscribe', [
            'email' => 'repeat@example.com',
            'source' => 'home_footer',
        ]);

        $secondResponse->assertOk();
        $secondResponse->assertJsonPath('status', 'already_subscribed');

        $this->assertDatabaseCount('newsletter_subscriptions', 1);
        $this->assertDatabaseHas('newsletter_subscriptions', [
            'email' => 'repeat@example.com',
            'source' => 'home_footer',
            'status' => 'subscribed',
        ]);
    }

    public function test_subscribe_validates_email(): void
    {
        $response = $this->postJson('/api/newsletter/subscribe', [
            'email' => 'wrong-email',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }
}
