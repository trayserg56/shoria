<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

trait ResolvesCartIdentity
{
    /**
     * Возвращает [user, session_id] для текущего запроса.
     *
     * $sessionFromPayload — session_id из тела запроса (приоритетнее query-параметра).
     * $errorMessage — сообщение если ни user, ни session_id не переданы.
     */
    protected function resolveIdentity(
        Request $request,
        ?string $sessionFromPayload = null,
        string $errorMessage = 'session_id обязателен.',
    ): array {
        $user = $this->resolveAuthenticatedUser($request);

        $sessionId = (string) (
            $sessionFromPayload
            ?? $request->input('session_id')
            ?? $request->query('session_id')
            ?? $request->header('X-Session-Id')
            ?? ''
        );

        if (! $user && $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => $errorMessage,
            ]);
        }

        if ($sessionId !== '' && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sessionId)) {
            throw ValidationException::withMessages([
                'session_id' => 'Некорректный session_id.',
            ]);
        }

        return [
            'user' => $user,
            'session_id' => $sessionId !== '' ? $sessionId : null,
        ];
    }

    protected function resolveAuthenticatedUser(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            return $user;
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
