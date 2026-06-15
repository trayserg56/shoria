<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\UserAddress;
use App\Support\Delivery\DeliveryGatewayRegistry;
use App\Support\Delivery\Gateways\CdekDeliveryGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    public function __construct(private DeliveryGatewayRegistry $deliveryGateways) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->presentAddresses($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $validated = $this->validatePayload($request);

        $address = $user->addresses()->create($this->withCityCode($validated));

        if ($validated['is_default'] ?? false) {
            $this->makeDefault($user->id, $address->id);
        }

        return response()->json(['data' => $this->presentAddresses($request)], 201);
    }

    public function update(Request $request, UserAddress $address): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $this->validatePayload($request);
        $address->update($this->withCityCode($validated, $address));

        if ($validated['is_default'] ?? false) {
            $this->makeDefault($user->id, $address->id);
        }

        return response()->json(['data' => $this->presentAddresses($request)]);
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($address->user_id !== $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $address->delete();

        return response()->json(['data' => $this->presentAddresses($request)]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function withCityCode(array $validated, ?UserAddress $existing = null): array
    {
        $cityChanged = ! $existing || $existing->city !== $validated['city'];

        return [
            'label' => $validated['label'],
            'city' => $validated['city'],
            'city_code' => $cityChanged ? $this->resolveCityCode($validated['city']) : $existing->city_code,
            'address' => $validated['address'],
            'is_default' => $validated['is_default'] ?? false,
        ];
    }

    private function resolveCityCode(string $cityName): ?int
    {
        $method = DeliveryMethod::query()
            ->with('provider')
            ->where('provider_code', 'cdek')
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $method || ! $method->provider) {
            return null;
        }

        $gateway = $this->deliveryGateways->for($method->provider);

        if (! $gateway instanceof CdekDeliveryGateway) {
            return null;
        }

        return $gateway->findCityCode($method->provider, $cityName);
    }

    private function makeDefault(int $userId, int $addressId): void
    {
        UserAddress::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $addressId)
            ->update(['is_default' => false]);
    }

    private function presentAddresses(Request $request): array
    {
        return $request->user('sanctum')->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserAddress $address): array => [
                'id' => $address->id,
                'label' => $address->label,
                'city' => $address->city,
                'city_code' => $address->city_code,
                'address' => $address->address,
                'is_default' => (bool) $address->is_default,
            ])
            ->all();
    }
}
