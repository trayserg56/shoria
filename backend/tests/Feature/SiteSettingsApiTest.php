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
            'support_email' => 'hi@test.store',
            'footer_legal_line' => '© Test Legal',
            'feature_flags' => [
                'loyalty' => true,
                'gift_certificates' => false,
                'wishlist' => true,
                'product_compare' => true,
            ],
        ]);

        $response = $this->getJson('/api/site-settings');

        $response->assertOk()
            ->assertJsonPath('logo_text', 'Test Store')
            ->assertJsonPath('logo_image_url', null)
            ->assertJsonPath('phone_display', '+7 900 111-22-33')
            ->assertJsonPath('phone_tel', '+79001112233')
            ->assertJsonPath('work_hours_short', 'Ежедневно 9–21')
            ->assertJsonPath('support_email', 'hi@test.store')
            ->assertJsonPath('footer_legal_line', '© Test Legal')
            ->assertJsonPath('feature_flags.gift_certificates', false)
            ->assertJsonPath('feature_flags.loyalty', true)
            ->assertJsonPath('theme.general.container_width_px', 1464)
            ->assertJsonPath('theme.footer.tone', 'muted')
            ->assertJsonPath('theme.header.variant', 'classic');
    }

    public function test_site_settings_merges_missing_feature_flag_keys_as_enabled(): void
    {
        SiteSetting::query()->whereKey(1)->update([
            'feature_flags' => ['gift_certificates' => false],
        ]);

        $response = $this->getJson('/api/site-settings');

        $response->assertOk()
            ->assertJsonPath('feature_flags.gift_certificates', false)
            ->assertJsonPath('feature_flags.loyalty', true)
            ->assertJsonPath('feature_flags.wishlist', true)
            ->assertJsonPath('feature_flags.product_compare', true);
    }

    public function test_site_settings_merges_partial_theme_with_defaults(): void
    {
        SiteSetting::query()->whereKey(1)->update([
            'theme' => [
                'general' => [
                    'container_width_px' => 1296,
                    'primary_hex' => '#b91c1c',
                ],
                'home' => [
                    'sections' => [
                        'hero' => ['enabled' => false],
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/site-settings');

        $response->assertOk()
            ->assertJsonPath('theme.general.container_width_px', 1296)
            ->assertJsonPath('theme.general.primary_hex', '#b91c1c')
            ->assertJsonPath('theme.general.body_font', 'manrope')
            ->assertJsonPath('theme.home.sections.hero.enabled', false)
            ->assertJsonPath('theme.home.sections.news.enabled', true);
    }
}
