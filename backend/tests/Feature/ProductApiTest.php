<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_returns_seo_fields(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $product = Product::query()->where('slug', 'road-tempo-elite')->firstOrFail();

        $response = $this->getJson('/api/products/' . $product->slug);

        $response->assertOk();
        $response->assertJsonPath('slug', $product->slug);
        $response->assertJsonPath('seo_title', 'Road Tempo Elite — беговые кроссовки для темпа');
        $response->assertJsonPath('seo_description', 'Road Tempo Elite: беговая модель для асфальта, быстрых тренировок и высокой отзывчивости.');
    }

    public function test_product_show_can_resolve_variant_slug_and_custom_images(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products/sprint-form-pro?variant=sunset-eu-40');
        $response->assertOk();
        $response->assertJsonPath('selected_variant_slug', 'sunset-eu-40');

        $payload = $response->json();

        $this->assertIsArray($payload['variants'] ?? null);
        $this->assertNotEmpty($payload['variants']);
        $this->assertSame(
            'sunset-eu-40',
            collect($payload['variants'])->firstWhere('slug', 'sunset-eu-40')['slug'] ?? null,
        );
        $this->assertNotEmpty($payload['images'] ?? []);
    }
}
