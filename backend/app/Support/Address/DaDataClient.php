<?php

namespace App\Support\Address;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DaDataClient
{
    private const SUGGEST_URL = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';

    /**
     * @return list<array{value: string, city: string, street: string, lat: float|null, lon: float|null}>
     */
    public function suggest(string $query, int $limit = 7): array
    {
        $apiKey = config('services.dadata.api_key');

        if (empty($apiKey)) {
            return $this->mockSuggest($query, $limit);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(self::SUGGEST_URL, [
                'query' => $query,
                'count' => $limit,
            ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('suggestions', []))
                ->map(function (array $item): array {
                    $data = $item['data'] ?? [];

                    return [
                        'value' => (string) ($item['value'] ?? ''),
                        'city' => (string) ($data['city'] ?? $data['settlement'] ?? ''),
                        'street' => $this->buildStreet($data),
                        'lat' => isset($data['geo_lat']) ? (float) $data['geo_lat'] : null,
                        'lon' => isset($data['geo_lon']) ? (float) $data['geo_lon'] : null,
                    ];
                })
                ->filter(fn (array $item) => $item['value'] !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('DaData suggest failed', ['error' => $e->getMessage()]);

            return $this->mockSuggest($query, $limit);
        }
    }

    private function buildStreet(array $data): string
    {
        $parts = array_filter([
            $data['street_with_type'] ?? null,
            $data['house'] ?? null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Мок-режим: статичный датасет реалистичных адресов для разработки и демо
     * без ключа DaData. Поиск по подстроке: приоритет — совпадение с начала
     * слова (регистронезависимо).
     *
     * @return list<array{value: string, city: string, street: string, lat: float, lon: float}>
     */
    private function mockSuggest(string $query, int $limit): array
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return [];
        }

        return collect(self::dataset())
            ->map(fn (array $item) => [...$item, '_score' => $this->matchScore(mb_strtolower($item['value']), $needle)])
            ->filter(fn (array $item) => $item['_score'] > 0)
            ->sortByDesc('_score')
            ->take($limit)
            ->map(fn (array $item) => collect($item)->except('_score')->all())
            ->values()
            ->all();
    }

    private function matchScore(string $haystack, string $needle): int
    {
        if (str_starts_with($haystack, $needle)) {
            return 3;
        }

        foreach (preg_split('/[\s,]+/u', $haystack) ?: [] as $word) {
            if (str_starts_with($word, $needle)) {
                return 2;
            }
        }

        if (mb_stripos($haystack, $needle) !== false) {
            return 1;
        }

        return 0;
    }

    /**
     * @return list<array{value: string, city: string, street: string, lat: float, lon: float}>
     */
    private static function dataset(): array
    {
        return [
            ['value' => 'г Оренбург, ул Транспортная, д 1/1', 'city' => 'Оренбург', 'street' => 'Транспортная, 1/1', 'lat' => 51.7727, 'lon' => 55.0988],
            ['value' => 'г Оренбург, ул Транспортная, д 5', 'city' => 'Оренбург', 'street' => 'Транспортная, 5', 'lat' => 51.7735, 'lon' => 55.1002],
            ['value' => 'г Оренбург, пр-кт Победы, д 12', 'city' => 'Оренбург', 'street' => 'проспект Победы, 12', 'lat' => 51.7800, 'lon' => 55.1100],
            ['value' => 'г Оренбург, ул Юных Ленинцев, д 5', 'city' => 'Оренбург', 'street' => 'Юных Ленинцев, 5', 'lat' => 51.7650, 'lon' => 55.0950],
            ['value' => 'г Оренбург, ул Кобозева, д 17', 'city' => 'Оренбург', 'street' => 'Кобозева, 17', 'lat' => 51.7680, 'lon' => 55.1050],
            ['value' => 'г Москва, ул Тверская, д 1', 'city' => 'Москва', 'street' => 'Тверская, 1', 'lat' => 55.7575, 'lon' => 37.6147],
            ['value' => 'г Москва, ул Тверская, д 12', 'city' => 'Москва', 'street' => 'Тверская, 12', 'lat' => 55.7625, 'lon' => 37.6080],
            ['value' => 'г Москва, ул Арбат, д 10', 'city' => 'Москва', 'street' => 'Арбат, 10', 'lat' => 55.7510, 'lon' => 37.5950],
            ['value' => 'г Москва, Ленинский пр-кт, д 32', 'city' => 'Москва', 'street' => 'Ленинский проспект, 32', 'lat' => 55.7035, 'lon' => 37.5700],
            ['value' => 'г Москва, ул Профсоюзная, д 45', 'city' => 'Москва', 'street' => 'Профсоюзная, 45', 'lat' => 55.6650, 'lon' => 37.5450],
            ['value' => 'г Санкт-Петербург, Невский пр-кт, д 28', 'city' => 'Санкт-Петербург', 'street' => 'Невский проспект, 28', 'lat' => 59.9343, 'lon' => 30.3351],
            ['value' => 'г Санкт-Петербург, ул Транспортная, д 3', 'city' => 'Санкт-Петербург', 'street' => 'Транспортная, 3', 'lat' => 59.8944, 'lon' => 30.2730],
            ['value' => 'г Санкт-Петербург, Лиговский пр-кт, д 50', 'city' => 'Санкт-Петербург', 'street' => 'Лиговский проспект, 50', 'lat' => 59.9210, 'lon' => 30.3550],
            ['value' => 'г Екатеринбург, ул Малышева, д 51', 'city' => 'Екатеринбург', 'street' => 'Малышева, 51', 'lat' => 56.8389, 'lon' => 60.6057],
            ['value' => 'г Екатеринбург, пр-кт Ленина, д 24', 'city' => 'Екатеринбург', 'street' => 'проспект Ленина, 24', 'lat' => 56.8380, 'lon' => 60.6020],
            ['value' => 'г Новосибирск, Красный пр-кт, д 17', 'city' => 'Новосибирск', 'street' => 'Красный проспект, 17', 'lat' => 55.0350, 'lon' => 82.9200],
            ['value' => 'г Новосибирск, ул Транспортная, д 9', 'city' => 'Новосибирск', 'street' => 'Транспортная, 9', 'lat' => 55.0150, 'lon' => 82.8900],
            ['value' => 'г Казань, ул Баумана, д 1', 'city' => 'Казань', 'street' => 'Баумана, 1', 'lat' => 55.7887, 'lon' => 49.1221],
            ['value' => 'г Казань, ул Кремлёвская, д 2', 'city' => 'Казань', 'street' => 'Кремлёвская, 2', 'lat' => 55.7965, 'lon' => 49.1064],
            ['value' => 'г Краснодар, ул Красная, д 100', 'city' => 'Краснодар', 'street' => 'Красная, 100', 'lat' => 45.0355, 'lon' => 38.9753],
        ];
    }
}
