<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_settings_endpoint_returns_payload(): void
    {
        SiteSetting::query()->whereKey(1)->update([
            'logo_text' => 'Test Store',
            'logo_image_path' => null,
            'phone_display' => '+7 900 111-22-33',
            'phone_tel' => '+79001112233',
            'work_hours_short' => 'Ежедневно 9–21',
        ]);

        $response = $this->getJson('/api/site-settings');

        $response->assertOk()
            ->assertJsonPath('logo_text', 'Test Store')
            ->assertJsonPath('logo_image_url', null)
            ->assertJsonPath('phone_display', '+7 900 111-22-33')
            ->assertJsonPath('phone_tel', '+79001112233')
            ->assertJsonPath('work_hours_short', 'Ежедневно 9–21');
    }
}
