<?php

namespace Tests\Feature;

use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_endpoint_returns_nested_subcategories(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonFragment([
            'slug' => 'running',
            'name' => 'Running',
        ]);
        $response->assertJsonFragment([
            'slug' => 'road-running',
            'name' => 'Road Running',
        ]);

        $runningCategory = collect($response->json())
            ->firstWhere('slug', 'running');

        $this->assertNotNull($runningCategory);
        $this->assertContains('road-running', collect($runningCategory['subcategories'])->pluck('slug')->all());
        $this->assertSame('Running кроссовки — Shoria', $runningCategory['seo_title']);
    }
}
