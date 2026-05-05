<?php

namespace Tests\Feature;

use App\Models\Category;
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
        $response->assertJsonPath('brand', 'Nike');
        $response->assertJsonPath('seo_title', 'Road Tempo Elite — беговые кроссовки для темпа');
        $response->assertJsonPath('seo_description', 'Road Tempo Elite: беговая модель для асфальта, быстрых тренировок и высокой отзывчивости.');
        $response->assertJsonStructure([
            'characteristics' => [
                '*' => ['group', 'name', 'value'],
            ],
        ]);
        $this->assertNotEmpty($response->json('characteristics'));
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

    public function test_parent_category_facet_count_matches_unique_catalog_results(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $running = Category::query()->where('slug', 'running')->firstOrFail();
        $roadRunning = Category::query()->where('slug', 'road-running')->firstOrFail();
        $roadProduct = Product::query()->where('category_id', $roadRunning->id)->firstOrFail();

        $roadProduct->categories()->syncWithoutDetaching([$running->id]);

        $catalogResponse = $this->getJson('/api/products?category=running');
        $catalogResponse->assertOk();

        $facetsResponse = $this->getJson('/api/products');
        $facetsResponse->assertOk();

        $runningFacet = collect($facetsResponse->json('filters.categories'))
            ->firstWhere('slug', 'running');

        $this->assertNotNull($runningFacet);
        $this->assertSame($catalogResponse->json('total'), $runningFacet['count']);
    }
}
