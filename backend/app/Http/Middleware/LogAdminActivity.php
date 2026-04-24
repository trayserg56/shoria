<?php

namespace App\Http\Middleware;

use App\Models\AdminActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->log($request, $response);

        return $response;
    }

    private function log(Request $request, Response $response): void
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (! str_starts_with($request->path(), 'admin')) {
            return;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        [$entityType, $entityId] = $this->resolveEntityContext($request);

        try {
            AdminActivityLog::query()->create([
                'user_id' => $user->id,
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_status' => $response->getStatusCode(),
                'request_payload' => $this->sanitizePayload($request->all()),
            ]);
        } catch (\Throwable) {
            // Логирование не должно ломать пользовательский сценарий админки.
        }
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function resolveEntityContext(Request $request): array
    {
        $segments = $request->segments();
        $entityType = $segments[1] ?? null;

        if (! is_string($entityType) || trim($entityType) === '') {
            return [null, null];
        }

        $record = $request->route('record');
        if (is_numeric($record)) {
            return [$entityType, (int) $record];
        }

        $candidate = $segments[2] ?? null;
        if (is_numeric($candidate)) {
            return [$entityType, (int) $candidate];
        }

        return [$entityType, null];
    }

    private function sanitizePayload(array $payload): ?array
    {
        if ($payload === []) {
            return null;
        }

        $hiddenKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'secret',
            'secret_key',
            'api_key',
            'private_key',
        ];

        $sanitize = function (mixed $value, ?string $key = null) use (&$sanitize, $hiddenKeys): mixed {
            if (is_string($key) && in_array(mb_strtolower($key), $hiddenKeys, true)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                $result = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $sanitize(
                        $nestedValue,
                        is_string($nestedKey) ? $nestedKey : null,
                    );
                }

                return $result;
            }

            if (is_string($value)) {
                return mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'…' : $value;
            }

            return $value;
        };

        return $sanitize($payload);
    }
}
