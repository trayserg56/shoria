<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\DeliveryProvider;
use App\Support\Delivery\Gateways\CdekDeliveryGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private CdekDeliveryGateway $cdek)
    {
    }

    /**
     * Список ПВЗ СДЭК по городу.
     * GET /api/delivery/cdek/pickup-points?city=Москва
     */
    public function cdekPickupPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $provider = DeliveryProvider::query()
            ->where('code', 'cdek')
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            return response()->json(['data' => [], 'message' => 'СДЭК недоступен'], 200);
        }

        $cityCode = $this->cdek->findCityCode($provider, $validated['city']);

        if (! $cityCode) {
            return response()->json(['data' => [], 'message' => 'Город не найден']);
        }

        $points = $this->cdek->listPickupPoints($provider, [
            'city_code' => $cityCode,
            'type' => 'PVZ',
        ]);

        $data = collect($points)->map(fn (array $point) => [
            'code' => $point['code'] ?? null,
            'name' => $point['name'] ?? null,
            'address' => data_get($point, 'location.address_full') ?? data_get($point, 'location.address'),
            'work_time' => $point['work_time'] ?? null,
            'lat' => data_get($point, 'location.latitude'),
            'lon' => data_get($point, 'location.longitude'),
        ])->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Примерная стоимость и сроки доставки по городу для всех активных способов.
     * GET /api/delivery/estimate?city=Москва
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $methods = DeliveryMethod::query()
            ->with('provider')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $data = $methods->map(function (DeliveryMethod $method) use ($validated): array {
            $result = [
                'code' => $method->code,
                'name' => $method->name,
                'fee' => (float) $method->fee,
                'period_min' => null,
                'period_max' => null,
            ];

            if ($method->provider_code === 'cdek' && $method->provider?->is_active) {
                $cityCode = $this->cdek->findCityCode($method->provider, $validated['city']);

                if ($cityCode) {
                    $tariff = $this->cdek->calculateTariff($method->provider, $method, $cityCode);

                    if ($tariff) {
                        $result['fee'] = $tariff['fee'];
                        $result['period_min'] = $tariff['period_min'];
                        $result['period_max'] = $tariff['period_max'];
                    }
                }
            }

            return $result;
        })->values();

        return response()->json(['data' => $data]);
    }
}
