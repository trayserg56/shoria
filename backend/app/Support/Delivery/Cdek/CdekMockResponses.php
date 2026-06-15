<?php

namespace App\Support\Delivery\Cdek;

/**
 * Реалистичные тестовые ответы СДЭК для режима без реальных учётных данных API.
 * Используется, пока нет account/secure_password (mock-режим провайдера).
 */
class CdekMockResponses
{
    /**
     * Известные коды городов СДЭК (реальные значения справочника локаций).
     */
    private const CITY_CODES = [
        'москва' => 44,
        'санкт-петербург' => 137,
        'спб' => 137,
        'новосибирск' => 270,
        'екатеринбург' => 250,
        'казань' => 63,
        'нижний новгород' => 88,
        'краснодар' => 270,
        'самара' => 14,
        'ростов-на-дону' => 161,
    ];

    /**
     * Если город неизвестен — возвращаем детерминированный псевдокод
     * (чтобы один и тот же город всегда давал один и тот же результат).
     */
    public static function cityCode(string $cityName): int
    {
        $key = mb_strtolower(trim($cityName));

        if (isset(self::CITY_CODES[$key])) {
            return self::CITY_CODES[$key];
        }

        // Псевдокод вне диапазона реальных кодов СДЭК, но стабильный для города
        return 900000 + (crc32($key) % 1000);
    }

    public static function tariffList(int $fromCityCode, int $toCityCode, int $tariffCode, int $weightGrams): array
    {
        // Базовая ставка + надбавка за вес и условную "дальность" (разница кодов городов)
        $base = $tariffCode === 138 ? 250 : 390; // ПВЗ дешевле курьера
        $distancePenalty = abs($toCityCode - $fromCityCode) % 50 * 8;
        $weightPenalty = (int) (max(0, $weightGrams - 1000) / 500) * 30;

        $sum = $base + $distancePenalty + $weightPenalty;

        $periodMin = $toCityCode === $fromCityCode ? 1 : 2 + (abs($toCityCode - $fromCityCode) % 5);
        $periodMax = $periodMin + 2;

        return [
            'tariff_codes' => [
                [
                    'tariff_code' => $tariffCode,
                    'tariff_name' => $tariffCode === 138 ? 'Посылка склад-склад' : 'Посылка склад-дверь',
                    'delivery_sum' => (float) $sum,
                    'period_min' => $periodMin,
                    'period_max' => $periodMax,
                ],
            ],
        ];
    }

    public static function pickupPoints(int $cityCode): array
    {
        $names = [
            'ТЦ «Центральный», 1 этаж',
            'ул. Ленина, 24, пункт выдачи',
            'ТЦ «Галерея», -1 этаж',
            'просп. Мира, 58, пункт выдачи СДЭК',
        ];

        $points = [];

        foreach ($names as $i => $name) {
            $points[] = [
                'code' => "MOCK{$cityCode}_".($i + 1),
                'name' => $name,
                'location' => [
                    'address_full' => $name,
                    'latitude' => 55.75 + ($i * 0.01),
                    'longitude' => 37.61 + ($i * 0.01),
                ],
                'work_time' => 'Пн-Вс 09:00-21:00',
            ];
        }

        return $points;
    }
}
