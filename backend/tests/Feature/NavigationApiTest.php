<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_endpoint_returns_grouped_menu_items(): void
    {
        $response = $this->getJson('/api/navigation');

        $response->assertOk();
        $response->assertJsonStructure([
            'header' => [
                '*' => ['id', 'label', 'path', 'is_external', 'open_in_new_tab'],
            ],
            'footer' => [
                'customers' => [
                    '*' => ['id', 'label', 'path', 'is_external', 'open_in_new_tab'],
                ],
                'account' => [
                    '*' => ['id', 'label', 'path', 'is_external', 'open_in_new_tab'],
                ],
            ],
        ]);

        $this->assertNotEmpty($response->json('header'));
        $this->assertNotEmpty($response->json('footer.customers'));
        $this->assertNotEmpty($response->json('footer.account'));
    }
}

