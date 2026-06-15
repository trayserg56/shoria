<?php

namespace App\Support\Delivery\Cdek;

use App\Models\DeliveryProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий клиент к API СДЭК v2.
 * Тестовый контур (sandbox) доступен бесплатно по client_id/client_secret из ЛК СДЭК — без договора.
 * Документация: https://api-docs.cdek.ru/
 */
class CdekApiClient
{
    private const SANDBOX_BASE_URL = 'https://api.edu.cdek.ru/v2';

    private const LIVE_BASE_URL = 'https://api.cdek.ru/v2';

    public function baseUrl(DeliveryProvider $provider): string
    {
        return $provider->mode === 'sandbox' ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
    }

    /**
     * Mock-режим: используется, пока нет реальных учётных данных API СДЭК
     * (личный кабинет требует ИНН юрлица/ИП). Включается явно флагом `mock: true`
     * в конфиге провайдера — позволяет разрабатывать ПВЗ-виджет и checkout
     * с реалистичными тестовыми данными без подключения к реальному API.
     */
    public function isMock(DeliveryProvider $provider): bool
    {
        return (bool) data_get($provider->config, 'mock', false);
    }

    /**
     * OAuth2 client_credentials — токен кэшируется на время жизни минус запас.
     */
    public function getAccessToken(DeliveryProvider $provider): ?string
    {
        if ($this->isMock($provider)) {
            return 'mock-access-token';
        }

        $clientId = data_get($provider->config, 'account');
        $clientSecret = data_get($provider->config, 'secure_password');

        if (! $clientId || ! $clientSecret) {
            Log::warning('CDEK: не заданы account/secure_password в конфиге провайдера', [
                'provider' => $provider->code,
            ]);

            return null;
        }

        $cacheKey = "cdek:access_token:{$provider->code}:{$provider->mode}";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($provider, $clientId, $clientSecret) {
            $response = Http::asForm()->post($this->baseUrl($provider).'/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (! $response->successful()) {
                Log::warning('CDEK: не удалось получить access_token', [
                    'provider' => $provider->code,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Расчёт стоимости и сроков доставки по тарифам.
     * Документация: POST /calculator/tarifflist
     */
    public function calculateTariffList(DeliveryProvider $provider, array $payload): ?array
    {
        if ($this->isMock($provider)) {
            return CdekMockResponses::tariffList(
                (int) data_get($payload, 'from_location.code'),
                (int) data_get($payload, 'to_location.code'),
                (int) data_get($payload, 'tariff_code'),
                (int) data_get($payload, 'packages.0.weight', 1000),
            );
        }

        $token = $this->getAccessToken($provider);

        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)
            ->post($this->baseUrl($provider).'/calculator/tarifflist', $payload);

        if (! $response->successful()) {
            Log::warning('CDEK: ошибка расчёта тарифов', [
                'provider' => $provider->code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Список ПВЗ. Документация: GET /deliverypoints
     */
    public function listPickupPoints(DeliveryProvider $provider, array $params): ?array
    {
        if ($this->isMock($provider)) {
            return CdekMockResponses::pickupPoints((int) data_get($params, 'city_code', 44));
        }

        $token = $this->getAccessToken($provider);

        if (! $token) {
            return null;
        }

        $cacheKey = 'cdek:pickup-points:'.$provider->code.':'.md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($provider, $token, $params) {
            $response = Http::withToken($token)
                ->get($this->baseUrl($provider).'/deliverypoints', $params);

            if (! $response->successful()) {
                Log::warning('CDEK: ошибка получения списка ПВЗ', [
                    'provider' => $provider->code,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        });
    }

    /**
     * Поиск кода города по названию. Документация: GET /location/cities
     */
    public function findCityCode(DeliveryProvider $provider, string $cityName): ?int
    {
        if ($this->isMock($provider)) {
            return CdekMockResponses::cityCode($cityName);
        }

        $token = $this->getAccessToken($provider);

        if (! $token) {
            return null;
        }

        $cacheKey = 'cdek:city-code:'.$provider->code.':'.mb_strtolower($cityName);

        return Cache::remember($cacheKey, now()->addWeek(), function () use ($provider, $token, $cityName) {
            $response = Http::withToken($token)
                ->get($this->baseUrl($provider).'/location/cities', [
                    'city' => $cityName,
                    'size' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            return data_get($response->json(), '0.code');
        });
    }
}
