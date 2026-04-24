<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited_after_threshold(): void
    {
        User::factory()->create([
            'email' => 'rate.limit@example.com',
            'password' => 'secret123',
        ]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'rate.limit@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        }

        $blocked = $this->postJson('/api/auth/login', [
            'email' => 'rate.limit@example.com',
            'password' => 'wrong-password',
        ]);

        $blocked->assertStatus(429);
    }
}
