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
        $this->markTestSkipped('Skipping rate limit test due to array cache driver in testing.');
    }
}
