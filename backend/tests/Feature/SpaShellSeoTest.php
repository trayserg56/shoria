<?php

namespace Tests\Feature;

use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaShellSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        copy(__DIR__.'/../Fixtures/spa-index.html', public_path('spa-index.html'));
    }

    public function test_home_html_contains_server_meta(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<html lang="ru">', false);
        $response->assertSee('Shoria — интернет-магазин', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('SearchAction', false);
        $response->assertSee('catalog?q={search_term_string}', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('/assets/index-fixture.js', false);
    }

    public function test_product_page_title_matches_storefront_pattern(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->get('/product/running/aero-pulse-300');

        $response->assertOk();
        $response->assertSee('<title>Aero Pulse 300 — Running · Shoria</title>', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('<link rel="canonical"', false);
    }

    public function test_cart_initial_html_is_noindex(): void
    {
        $response = $this->get('/cart');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex,nofollow"', false);
    }
}
