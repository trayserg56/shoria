<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_me_logout_and_login_again(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Sergei Tester',
            'email' => 'sergei.auth@example.com',
            'password' => 'secret123',
        ]);

        $registerResponse->assertCreated();
        $registerResponse->assertJsonPath('user.email', 'sergei.auth@example.com');

        $token = $registerResponse->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('users', [
            'email' => 'sergei.auth@example.com',
            'name' => 'Sergei Tester',
        ]);

        $meResponse = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);
        $meResponse->assertOk();
        $meResponse->assertJsonPath('user.email', 'sergei.auth@example.com');

        $logoutResponse = $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);
        $logoutResponse->assertOk();
        $logoutResponse->assertJsonPath('ok', true);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'sergei.auth@example.com',
            'password' => 'secret123',
        ]);
        $loginResponse->assertOk();
        $loginResponse->assertJsonPath('user.email', 'sergei.auth@example.com');
        $this->assertNotSame($token, $loginResponse->json('token'));
    }

    public function test_guest_cart_is_adopted_by_user_and_orders_are_available_via_auth(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-auth-adopt';

        $addItemResponse = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 2,
        ]);
        $addItemResponse->assertOk();
        $addItemResponse->assertJsonPath('total_items', 2);

        $user = User::factory()->create([
            'name' => 'Auth Buyer',
            'email' => 'auth.buyer@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'auth.buyer@example.com',
            'password' => 'secret123',
        ]);
        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        $cartAsAuthResponse = $this->getJson("/api/cart?session_id={$sessionId}", [
            'Authorization' => "Bearer {$token}",
        ]);
        $cartAsAuthResponse->assertOk();
        $cartAsAuthResponse->assertJsonPath('total_items', 2);

        $this->assertDatabaseHas('carts', [
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $checkoutResponse = $this->postJson('/api/checkout', [
            'customer_name' => 'Auth Buyer',
            'customer_email' => 'auth.buyer@example.com',
            'customer_phone' => '+79991112233',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $checkoutResponse->assertCreated();
        $orderNumber = $checkoutResponse->json('order_number');

        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'customer_email' => 'auth.buyer@example.com',
        ]);

        $ordersAsAuthResponse = $this->getJson('/api/orders', [
            'Authorization' => "Bearer {$token}",
        ]);
        $ordersAsAuthResponse->assertOk();
        $ordersAsAuthResponse->assertJsonPath('total', 1);
        $ordersAsAuthResponse->assertJsonPath('data.0.order_number', $orderNumber);

        $orderDetailsAsAuthResponse = $this->getJson("/api/orders/{$orderNumber}", [
            'Authorization' => "Bearer {$token}",
        ]);
        $orderDetailsAsAuthResponse->assertOk();
        $orderDetailsAsAuthResponse->assertJsonPath('order_number', $orderNumber);
    }

    public function test_forgot_password_sends_notification_and_reset_password_updates_credentials(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'recover@example.com',
            'password' => 'old-password',
        ]);

        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => 'recover@example.com',
        ]);
        $forgotResponse->assertOk();
        $forgotResponse->assertJsonPath('ok', true);

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);

        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'recover@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);
        $resetResponse->assertOk();
        $resetResponse->assertJsonPath('ok', true);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'recover@example.com',
            'password' => 'new-password-123',
        ]);
        $loginResponse->assertOk();
        $loginResponse->assertJsonPath('user.email', 'recover@example.com');
    }

    public function test_register_sends_email_verification_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Verify User',
            'email' => 'verify@example.com',
            'password' => 'secret123',
        ]);
        $response->assertCreated();

        $user = User::query()->where('email', 'verify@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_authenticated_user_can_request_verification_email_and_signed_url_verifies_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('token');

        $resendResponse = $this->postJson('/api/auth/email/verification-notification', [], [
            'Authorization' => "Bearer {$token}",
        ]);
        $resendResponse->assertOk();
        $resendResponse->assertJsonPath('ok', true);

        Notification::assertSentTo($user, VerifyEmail::class);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $verifyResponse = $this->getJson($verificationUrl);
        $verifyResponse->assertRedirect();

        $location = $verifyResponse->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/account/settings', $location);

        $parsed = parse_url($location);
        parse_str($parsed['query'] ?? '', $query);
        $this->assertSame('1', $query['verified'] ?? null);
        $this->assertSame('success', $query['reason'] ?? null);

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_authenticated_user_can_update_profile_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old-profile@example.com',
            'phone' => null,
            'password' => 'secret123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'old-profile@example.com',
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('token');

        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'New Name',
            'email' => 'new-profile@example.com',
            'phone' => '+7 (900) 111-22-33',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('user.name', 'New Name');
        $response->assertJsonPath('user.email', 'new-profile@example.com');
        $response->assertJsonPath('user.phone', '+7 (900) 111-22-33');
        $response->assertJsonPath('user.email_verified_at', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new-profile@example.com',
            'phone' => '+7 (900) 111-22-33',
        ]);
    }
}
