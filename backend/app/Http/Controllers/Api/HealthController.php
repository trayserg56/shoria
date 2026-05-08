<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Проверка готовности для мониторинга / балансировщиков (без тяжёлых запросов).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
            'database' => false,
            'cache' => false,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            $store = Cache::store(config('cache.default'));
            $store->put('__health_probe', '1', 5);
            $checks['cache'] = $store->get('__health_probe') === '1';
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        $ok = $checks['database'] && $checks['cache'];
        $status = $ok ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }
}
