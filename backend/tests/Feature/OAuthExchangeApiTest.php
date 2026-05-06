<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OAuthExchangeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_rejects_unknown_code(): void
    {
        $response = $this->postJson('/api/auth/oauth/exchange', [
            'code' => str_repeat('a', 48),
        ]);

        $response->assertStatus(422)
            ->assertInvalid('code');
    }

    public function test_exchange_returns_token_when_code_valid_and_single_use(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $plainToken = $user->createToken('spa')->plainTextToken;
        $code = str_repeat('b', 48);

        Cache::put('oauth_exchange:'.$code, [
            'token' => $plainToken,
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        $response = $this->postJson('/api/auth/oauth/exchange', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'name'],
            ]);

        $this->postJson('/api/auth/oauth/exchange', ['code' => $code])
            ->assertStatus(422);
    }
}
