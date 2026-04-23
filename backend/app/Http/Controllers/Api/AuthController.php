<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => User::ROLE_CUSTOMER,
            'password' => $validated['password'],
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $this->resolveUserFromToken($request);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $this->resolveUserFromToken($request);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'ok' => true,
            'status' => $emailChanged
                ? 'Профиль обновлен. Подтвердите новый email по ссылке из письма.'
                : 'Профиль обновлен.',
            'user' => $this->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $this->resolveUserFromToken($request);
        $token = $request->bearerToken();

        if ($user && $token) {
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken && $accessToken->tokenable_type === User::class && (int) $accessToken->tokenable_id === $user->id) {
                $accessToken->delete();
            }
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'ok' => true,
            'status' => __($status),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:120', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return response()->json([
            'ok' => true,
            'status' => __($status),
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $this->resolveUserFromToken($request);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => true,
                'status' => 'Email уже подтвержден.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'ok' => true,
            'status' => 'Ссылка для подтверждения отправлена на email.',
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->away($this->buildFrontendAccountUrl([
                'verified' => '0',
                'reason' => 'invalid_signature',
            ]));
        }

        $user = User::query()->find($id);

        if (! $user) {
            return redirect()->away($this->buildFrontendAccountUrl([
                'verified' => '0',
                'reason' => 'user_not_found',
            ]));
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away($this->buildFrontendAccountUrl([
                'verified' => '0',
                'reason' => 'invalid_hash',
            ]));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            return redirect()->away($this->buildFrontendAccountUrl([
                'verified' => '1',
                'reason' => 'success',
            ]));
        }

        return redirect()->away($this->buildFrontendAccountUrl([
            'verified' => '1',
            'reason' => 'already_verified',
        ]));
    }

    private function resolveUserFromToken(Request $request): ?User
    {
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

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at?->toJSON(),
        ];
    }

    private function buildFrontendAccountUrl(array $query): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $baseUrl . '/account/settings?' . http_build_query($query);
    }
}
