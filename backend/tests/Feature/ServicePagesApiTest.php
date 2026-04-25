<?php

namespace Tests\Feature;

use App\Models\ServicePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_index_returns_only_active_service_pages(): void
    {
        $initialActiveCount = ServicePage::query()
            ->where('is_active', true)
            ->count();

        ServicePage::query()->create([
            'title' => 'Активная страница',
            'slug' => 'active-page-test',
            'is_active' => true,
            'show_in_header' => true,
            'show_in_footer' => true,
            'sort_order' => 10,
        ]);

        ServicePage::query()->create([
            'title' => 'Черновик страницы',
            'slug' => 'draft-page',
            'is_active' => false,
            'show_in_header' => true,
            'show_in_footer' => true,
            'sort_order' => 20,
        ]);

        $response = $this->getJson('/api/pages');

        $response->assertOk();
        $response->assertJsonCount($initialActiveCount + 1);
        $response->assertJsonFragment([
            'slug' => 'active-page-test',
            'title' => 'Активная страница',
        ]);
    }

    public function test_pages_show_returns_active_page_by_slug(): void
    {
        ServicePage::query()->create([
            'title' => 'Тестовая служебная страница',
            'slug' => 'custom-delivery-page',
            'excerpt' => 'Сроки и способы доставки',
            'content' => '<p>Тестовый контент</p>',
            'seo_title' => 'Доставка — Shoria',
            'seo_description' => 'Тест',
            'is_active' => true,
            'show_in_header' => true,
            'show_in_footer' => true,
            'sort_order' => 10,
        ]);

        $response = $this->getJson('/api/pages/custom-delivery-page');

        $response->assertOk();
        $response->assertJsonPath('slug', 'custom-delivery-page');
        $response->assertJsonStructure([
            'id',
            'title',
            'slug',
            'excerpt',
            'content',
            'seo_title',
            'seo_description',
            'updated_at',
        ]);
    }

    public function test_pages_show_returns_not_found_for_inactive_page(): void
    {
        ServicePage::query()->create([
            'title' => 'Скрытая страница',
            'slug' => 'hidden-page',
            'is_active' => false,
            'show_in_header' => false,
            'show_in_footer' => false,
            'sort_order' => 10,
        ]);

        $response = $this->getJson('/api/pages/hidden-page');

        $response->assertNotFound();
    }
}
