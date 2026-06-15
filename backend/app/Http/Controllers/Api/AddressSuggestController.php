<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Address\DaDataClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressSuggestController extends Controller
{
    public function __construct(private DaDataClient $daData)
    {
    }

    /**
     * Подсказки адреса по введённой строке.
     * GET /api/address/suggest?q=...
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        return response()->json([
            'data' => $this->daData->suggest($validated['q']),
        ]);
    }
}
