<?php

namespace Tests\Feature;

use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_brands_endpoint_returns_active_brands_with_products_count(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/brands');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'slug',
                'image_url',
                'products_count',
            ],
        ]);

        $nike = collect($response->json())->firstWhere('slug', 'nike');
        $this->assertNotNull($nike);
        $this->assertGreaterThan(0, (int) $nike['products_count']);
    }
}
