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
}
