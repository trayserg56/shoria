<?php

namespace App\Support\Store;

/**
 * Настройки темы витрины (site_settings.theme).
 *
 * @phpstan-type ThemeSectionState array{enabled: bool}
 */
final class StoreTheme
{
    /**
     * @return array<string, string>
     */
    public static function homeSectionLabels(): array
    {
        return [
            'hero' => 'Главный баннер',
            'trust' => 'Блок преимуществ',
            'marketing' => 'Подборки и акции',
            'categories' => 'Категории',
            'brands' => 'Бренды',
            'featured' => 'Рекомендуем',
            'recent' => 'Недавно просмотренные',
            'why' => '«Почему мы»',
            'newsletter' => 'Подписка на рассылку',
            'news' => 'Новости',
        ];
    }

    /**
     * @return array<string, ThemeSectionState>
     */
    private static function defaultHomeSections(): array
    {
        $out = [];
        foreach (array_keys(self::homeSectionLabels()) as $key) {
            $out[$key] = ['enabled' => true];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'general' => [
                'container_width_px' => 1464,
                'body_font' => 'manrope',
                'display_font' => 'bebas',
                'base_font_size_px' => 16,
                'use_display_for_headings' => true,
                'button_radius_px' => 12,
                'heading_weight' => '700',
                'primary_hex' => '#09090b',
                'primary_foreground_hex' => '#fafafa',
            ],
            'header' => [
                'sticky' => true,
                'variant' => 'classic',
            ],
            'footer' => [
                'tone' => 'muted',
            ],
            'catalog' => [
                'grid_density' => 'comfortable',
            ],
            'home' => [
                'sections' => self::defaultHomeSections(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function merge(?array $stored): array
    {
        $base = self::defaults();

        if ($stored === null || $stored === []) {
            return $base;
        }

        $merged = array_replace_recursive($base, $stored);
        $merged['home']['sections'] = self::mergeHomeSections($merged['home']['sections'] ?? []);
        $merged['header'] = self::normalizeHeader($merged['header'] ?? []);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{sticky: bool, variant: string}
     */
    private static function normalizeHeader(array $header): array
    {
        $defaults = self::defaults()['header'];
        $allowedVariants = ['classic', 'centered', 'wide_search'];
        $variant = $header['variant'] ?? $defaults['variant'];
        if (! is_string($variant) || ! in_array($variant, $allowedVariants, true)) {
            $variant = $defaults['variant'];
        }

        return [
            'sticky' => isset($header['sticky']) ? (bool) $header['sticky'] : $defaults['sticky'],
            'variant' => $variant,
        ];
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, ThemeSectionState>
     */
    private static function mergeHomeSections(array $sections): array
    {
        $out = self::defaultHomeSections();
        foreach ($out as $key => $defaultState) {
            if (! isset($sections[$key]) || ! is_array($sections[$key])) {
                continue;
            }
            $row = $sections[$key];
            $out[$key] = [
                'enabled' => isset($row['enabled']) ? (bool) $row['enabled'] : $defaultState['enabled'],
            ];
        }

        return $out;
    }
}
