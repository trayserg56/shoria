<?php

namespace Tests\Feature;

use App\Models\NewsPost;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_show_returns_post_by_slug_with_related(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $post = NewsPost::query()->where('slug', 'white-sneakers-care')->firstOrFail();

        $response = $this->getJson('/api/news/' . $post->slug);

        $response->assertOk();
        $response->assertJsonPath('slug', $post->slug);
        $response->assertJsonStructure([
            'id',
            'title',
            'slug',
            'content_type',
            'excerpt',
            'content',
            'cover_url',
            'seo_title',
            'seo_description',
            'published_at',
            'related',
            'spotlight_products',
        ]);
        $response->assertJsonPath('seo_title', 'Как ухаживать за белыми кроссовками — советы Shoria');
        $response->assertJsonPath('content_type', 'news');
        $response->assertJsonCount(3, 'spotlight_products');
        $response->assertJsonFragment(['slug' => 'city-frame-one']);
        $response->assertJsonFragment(['slug' => 'cloud-step-v2']);
        $response->assertJsonFragment(['slug' => 'daily-ease']);
    }

    public function test_news_show_returns_not_found_for_unpublished_post(): void
    {
        $post = NewsPost::query()->create([
            'title' => 'Draft material',
            'slug' => 'draft-material',
            'excerpt' => 'Hidden',
            'content' => 'Hidden content',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/news/' . $post->slug);

        $response->assertNotFound();
    }

    public function test_news_index_can_be_filtered_by_content_type(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/news?type=guide');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', 'how-to-choose-running-shoes');
        $response->assertJsonPath('data.0.content_type', 'guide');
    }

    public function test_news_index_ignores_unknown_content_type_filter(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $allPublishedCount = NewsPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->count();

        $response = $this->getJson('/api/news?type=unknown');

        $response->assertOk();
        $response->assertJsonCount($allPublishedCount, 'data');
    }

    public function test_news_show_prefers_manual_related_products_over_auto_selection(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $post = NewsPost::query()->where('slug', 'how-to-choose-running-shoes')->firstOrFail();

        $response = $this->getJson('/api/news/' . $post->slug);

        $response->assertOk();
        $response->assertJsonCount(3, 'spotlight_products');
        $response->assertJsonFragment(['slug' => 'road-tempo-elite']);
        $response->assertJsonFragment(['slug' => 'sprint-form-pro']);
        $response->assertJsonFragment(['slug' => 'aero-pulse-300']);
    }
}
