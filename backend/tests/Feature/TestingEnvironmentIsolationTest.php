<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingEnvironmentIsolationTest extends TestCase
{
    public function test_test_runtime_uses_isolated_sqlite_database(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotSame('pgsql', config('database.default'));
    }
}

