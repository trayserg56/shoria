<?php

namespace Tests\Feature;

use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_endpoint_is_available(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Sitemap:', false);
    }

    public function test_sitemap_contains_catalog_and_products(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee('/catalog', false);
        $response->assertSee('/catalog/running', false);
        $response->assertSee('/brands', false);
        $response->assertSee('/loyalty-program', false);
        $response->assertSee('/news', false);
        $response->assertSee('/product/', false);
        $response->assertSee('/news/', false);
        $response->assertDontSee('/pages/loyalty-program', false);
    }

    public function test_loyalty_cms_alias_redirects_to_public_route(): void
    {
        $response = $this->get('/pages/loyalty-program');

        $response->assertRedirect('/loyalty-program');
    }
}
