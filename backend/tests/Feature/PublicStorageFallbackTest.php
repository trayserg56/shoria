<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storage_file_is_served_via_storage_route(): void
    {
        Storage::disk('public')->put('news-content/demo.txt', 'demo-content');

        $response = $this->get('/storage/news-content/demo.txt');

        $response->assertOk();
        $response->assertHeader('cache-control', 'max-age=604800, public');
    }
}
