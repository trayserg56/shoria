<?php

namespace App\Support\Analytics;

class AttributionData
{
    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, string|null>
     */
    public static function normalize(?array $input): array
    {
        return [
            'first_touch_source' => self::sanitize($input['source'] ?? null, 120),
            'first_touch_medium' => self::sanitize($input['medium'] ?? null, 120),
            'first_touch_campaign' => self::sanitize($input['campaign'] ?? null, 180),
            'first_touch_content' => self::sanitize($input['content'] ?? null, 180),
            'first_touch_term' => self::sanitize($input['term'] ?? null, 180),
            'first_touch_landing_path' => self::sanitize($input['landing_path'] ?? null, 2048),
            'first_touch_referrer_host' => self::sanitize($input['referrer_host'] ?? null, 255),
        ];
    }

    private static function sanitize(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, $limit);
    }
}
