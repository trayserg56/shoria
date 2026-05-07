<?php

namespace App\Support\Store;

/**
 * Ключи feature flags витрины (хранятся в site_settings.feature_flags).
 */
final class StoreFeatureFlags
{
    public const LOYALTY = 'loyalty';

    public const GIFT_CERTIFICATES = 'gift_certificates';

    public const WISHLIST = 'wishlist';

    public const PRODUCT_COMPARE = 'product_compare';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::LOYALTY,
            self::GIFT_CERTIFICATES,
            self::WISHLIST,
            self::PRODUCT_COMPARE,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return array_fill_keys(self::keys(), true);
    }

    /**
     * @return array<string, string> ключ => подпись для админки
     */
    public static function labels(): array
    {
        return [
            self::LOYALTY => 'Программа лояльности (списание/начисление, страницы и пункты меню)',
            self::GIFT_CERTIFICATES => 'Подарочные сертификаты (покупка, оплата заказа сертификатом)',
            self::WISHLIST => 'Избранное',
            self::PRODUCT_COMPARE => 'Сравнение товаров',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, bool>
     */
    public static function merge(?array $stored): array
    {
        $out = self::defaults();
        if (! is_array($stored)) {
            return $out;
        }

        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }
            $out[$key] = filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return $out;
    }
}
