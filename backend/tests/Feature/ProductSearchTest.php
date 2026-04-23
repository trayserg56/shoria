<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_supports_text_query_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?q=cloud');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'cloud-step-v2']);
        $response->assertJsonMissing(['slug' => 'city-frame-one']);
    }

    public function test_products_index_supports_cyrillic_query_via_transliteration(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?q=неон');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'neon-track-x']);
    }

    public function test_search_suggest_returns_matches_for_query(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/search/suggest?q=sp');

        $response->assertOk();
        $response->assertJsonPath('query', 'sp');
        $response->assertJsonFragment(['slug' => 'sprint-form-pro']);
    }

    public function test_search_suggest_supports_cyrillic_query_via_transliteration(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/search/suggest?q=не');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'neon-track-x']);
    }

    public function test_search_suggest_requires_minimal_query_length(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/search/suggest?q=s');

        $response->assertOk();
        $response->assertJsonPath('query', 's');
        $response->assertJsonCount(0, 'suggestions');
    }

    public function test_products_index_supports_price_filter_and_sort(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?price_min=12000&price_max=15000&sort=price_desc');

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', 'sprint-form-pro');
        $response->assertJsonFragment(['slug' => 'aero-pulse-300']);
        $response->assertJsonMissing(['slug' => 'city-frame-one']);
    }

    public function test_products_index_supports_in_stock_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        Product::query()
            ->where('slug', 'city-frame-one')
            ->update(['stock' => 0]);

        $response = $this->getJson('/api/products?in_stock=1');

        $response->assertOk();
        $response->assertJsonMissing(['slug' => 'city-frame-one']);
        $response->assertJsonFragment(['slug' => 'aero-pulse-300']);
    }

    public function test_products_index_supports_tags_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?tags=new');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'vault-signature']);
        $response->assertJsonMissing(['slug' => 'aero-pulse-300']);
    }

    public function test_products_include_tags_payload(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?tags=hit');

        $response->assertOk();
        $response->assertJsonPath('data.0.tags.0.code', 'hit');
        $response->assertJsonPath('data.0.tags.0.label', 'Хит');
    }

    public function test_products_index_supports_parent_category_filter_with_subcategories(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?category=running');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'aero-pulse-300']);
        $response->assertJsonFragment(['slug' => 'road-tempo-elite']);
        $response->assertJsonFragment(['slug' => 'trail-ridge-gtx']);
        $response->assertJsonMissing(['slug' => 'city-frame-one']);
    }

    public function test_products_index_supports_subcategory_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?category=road-running');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'road-tempo-elite']);
        $response->assertJsonFragment(['slug' => 'aero-pulse-300']);
        $response->assertJsonMissing(['slug' => 'trail-ridge-gtx']);
    }

    public function test_products_index_supports_brand_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?brands=Puma');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'neon-track-x']);
        $response->assertJsonMissing(['slug' => 'aero-pulse-300']);
    }

    public function test_products_index_supports_variant_color_and_size_filters(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?colors=Sunset&sizes=EU 40');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'sprint-form-pro']);
        $response->assertJsonMissing(['slug' => 'cloud-step-v2']);
        $response->assertJsonMissing(['slug' => 'city-frame-one']);
    }

    public function test_products_index_returns_dynamic_facets_for_active_filters(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products?colors=Sunset');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.slug', 'sprint-form-pro');
        $response->assertJsonFragment(['value' => 'Sunset']);
        $response->assertJsonFragment(['value' => 'Nike']);
        $response->assertJsonMissing(['value' => 'Puma']);
        $response->assertJsonFragment(['code' => 'customer_choice']);
    }
}
