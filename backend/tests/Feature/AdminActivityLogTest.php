<?php

namespace Tests\Feature;

use App\Http\Middleware\LogAdminActivity;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_write_request_is_logged_with_redacted_sensitive_fields(): void
    {
        Route::middleware(LogAdminActivity::class)
            ->post('/admin/testing-audit-log', fn () => response()->json(['ok' => true], 201));

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/testing-audit-log', [
                'title' => 'Test payload',
                'password' => 'super-secret-password',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->id,
            'method' => 'POST',
            'path' => 'admin/testing-audit-log',
            'response_status' => 201,
            'entity_type' => 'testing-audit-log',
        ]);

        $log = AdminActivityLog::query()->latest('id')->firstOrFail();
        $this->assertSame('Test payload', $log->request_payload['title'] ?? null);
        $this->assertSame('[REDACTED]', $log->request_payload['password'] ?? null);
    }
}
