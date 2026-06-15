<?php

namespace App\Support\Delivery\Gateways;

use App\Models\DeliveryMethod;
use App\Models\DeliveryProvider;
use App\Support\Delivery\Cdek\CdekApiClient;
use App\Support\Delivery\Contracts\DeliveryGateway;

class CdekDeliveryGateway implements DeliveryGateway
{
    public function __construct(private CdekApiClient $client)
    {
    }

    public function driver(): string
    {
        return 'cdek';
    }

    /**
     * Базовая логика: пока нет адреса доставки в заказе — отдаём
     * настроенную в админке fallback-стоимость метода (`fee`).
     * Расчёт через тарифный калькулятор СДЭК подключается на этапе
     * checkout v4, когда появится город/адрес доставки покупателя.
     */
    public function resolveFee(DeliveryProvider $provider, DeliveryMethod $method, float $subtotal): float
    {
        return (float) $method->fee;
    }

    /**
     * Расчёт стоимости и сроков по тарифам СДЭК для конкретного города назначения.
     * Возвращает null, если СДЭК недоступен/не настроен — вызывающий код
     * должен сделать fallback на resolveFee().
     */
    public function calculateTariff(DeliveryProvider $provider, DeliveryMethod $method, int $toCityCode, float $weightGrams = 1000): ?array
    {
        $fromCityCode = (int) data_get($provider->config, 'from_city_code', 44); // 44 = Москва по умолчанию

        $tariffCode = (int) ($method->external_code === 'pickup' ? 138 : 137); // 137 — курьер «дверь-дверь», 138 — ПВЗ «склад-склад»

        $result = $this->client->calculateTariffList($provider, [
            'from_location' => ['code' => $fromCityCode],
            'to_location' => ['code' => $toCityCode],
            'packages' => [
                ['weight' => max(1, (int) $weightGrams)],
            ],
            'tariff_code' => $tariffCode,
        ]);

        if (! $result || ! isset($result['tariff_codes'][0])) {
            return null;
        }

        $tariff = $result['tariff_codes'][0];

        return [
            'fee' => (float) ($tariff['delivery_sum'] ?? $method->fee),
            'period_min' => $tariff['period_min'] ?? null,
            'period_max' => $tariff['period_max'] ?? null,
        ];
    }

    public function findCityCode(DeliveryProvider $provider, string $cityName): ?int
    {
        return $this->client->findCityCode($provider, $cityName);
    }

    public function listPickupPoints(DeliveryProvider $provider, array $params): array
    {
        return $this->client->listPickupPoints($provider, $params) ?? [];
    }
}
