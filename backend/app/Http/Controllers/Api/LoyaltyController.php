<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Loyalty\LoyaltyProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private LoyaltyProgramService $loyaltyProgram,
    ) {
    }

    public function info(): JsonResponse
    {
        return response()->json($this->loyaltyProgram->infoPayload());
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $setting = $this->loyaltyProgram->getSetting();

        return response()->json([
            'program' => $this->loyaltyProgram->infoPayload($setting),
            'account' => $this->loyaltyProgram->userSnapshot($user, $setting),
            'history' => $this->loyaltyProgram->userHistory($user, 50),
        ]);
    }
}
