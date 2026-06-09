<?php

namespace App\Support\Store;

final class StoreFeedSettings
{
    /**
     * Дефолтные настройки YML-фида.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Основное
            'shop_name' => '',          // если пусто — берётся APP_NAME
            'company_name' => '',       // юридическое название
            'currency' => 'RUB',

            // Фильтрация товаров
            'excluded_category_ids' => [],  // ID категорий, которые НЕ попадают в фид
            'min_price' => 0,               // минимальная цена оффера (0 = без ограничения)
            'include_out_of_stock' => false, // включать товары с stock = 0

            // Доставка
            'sales_notes' => 'Доставка по всей России',

            // Дополнительно
            'enable_oldprice' => true,   // выводить зачёркнутую цену
            'max_images' => 10,          // максимум картинок на оффер (1–10)
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function merge(?array $stored): array
    {
        return array_merge(self::defaults(), $stored ?? []);
    }
}
