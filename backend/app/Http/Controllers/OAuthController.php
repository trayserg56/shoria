<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OAuthController extends Controller
{
    private const string PROVIDER_VK = 'vkontakte';
    private const string PROVIDER_YANDEX = 'yandex';

    public function redirectToVk(): RedirectResponse
    {
        if (! $this->vkConfigured()) {
            throw new HttpException(503, 'VK OAuth is not configured.');
        }

        return Socialite::driver(self::PROVIDER_VK)->scopes([])->redirect();
    }

    public function handleVkCallback(): RedirectResponse
    {
        if (! $this->vkConfigured()) {
            return $this->oauthErrorRedirect('not_configured');
        }

        try {
            $vkUser = Socialite::driver(self::PROVIDER_VK)->user();
        } catch (InvalidStateException) {
            return $this->oauthErrorRedirect('state');
        } catch (\Throwable $e) {
            report($e);

            return $this->oauthErrorRedirect('vk_error');
        }

        $providerId = (string) $vkUser->getId();
        $emailRaw = $vkUser->getEmail();
        $email = $emailRaw !== null && $emailRaw !== '' ? mb_strtolower(trim($emailRaw)) : null;
        $name = $vkUser->getName() ?: $vkUser->getNickname() ?: 'Покупатель';

        $user = User::query()
            ->where('oauth_provider', self::PROVIDER_VK)
            ->where('oauth_id', $providerId)
            ->first();

        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user) {
                $user->forceFill([
                    'oauth_provider' => self::PROVIDER_VK,
                    'oauth_id' => $providerId,
                ])->save();
            }
        }

        if (! $user) {
            if (! $email) {
                $email = sprintf('vk.%s.oauth@internal.localhost', $providerId);
                while (User::query()->where('email', $email)->exists()) {
                    $email = sprintf('vk.%s.%s.oauth@internal.localhost', $providerId, Str::lower(Str::random(6)));
                }
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'oauth_provider' => self::PROVIDER_VK,
                'oauth_id' => $providerId,
                'role' => User::ROLE_CUSTOMER,
                'password' => null,
                'email_verified_at' => now(),
            ]);
        } else {
            if ($user->name === '' || $user->name === 'Покупатель') {
                $user->forceFill(['name' => $name])->save();
            }

            if ($email && $vkUser->getEmail() && ! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        $plainToken = $user->createToken('spa')->plainTextToken;
        $code = Str::random(48);
        Cache::put('oauth_exchange:'.$code, [
            'token' => $plainToken,
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        $url = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/')
            .'/auth/oauth-callback?code='.urlencode($code);

        return redirect()->away($url);
    }

    public function redirectToYandex(): RedirectResponse
    {
        if (! $this->yandexConfigured()) {
            throw new HttpException(503, 'Yandex OAuth is not configured.');
        }

        return Socialite::driver(self::PROVIDER_YANDEX)->redirect();
    }

    public function handleYandexCallback(): RedirectResponse
    {
        if (! $this->yandexConfigured()) {
            return $this->oauthErrorRedirect('not_configured');
        }

        try {
            $yandexUser = Socialite::driver(self::PROVIDER_YANDEX)->user();
        } catch (InvalidStateException) {
            return $this->oauthErrorRedirect('state');
        } catch (\Throwable $e) {
            report($e);

            return $this->oauthErrorRedirect('yandex_error');
        }

        $providerId = (string) $yandexUser->getId();
        $emailRaw = $yandexUser->getEmail();
        $email = $emailRaw !== null && $emailRaw !== '' ? mb_strtolower(trim($emailRaw)) : null;
        $name = $yandexUser->getName() ?: $yandexUser->getNickname() ?: 'Покупатель';

        $user = User::query()
            ->where('oauth_provider', self::PROVIDER_YANDEX)
            ->where('oauth_id', $providerId)
            ->first();

        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user) {
                $user->forceFill([
                    'oauth_provider' => self::PROVIDER_YANDEX,
                    'oauth_id' => $providerId,
                ])->save();
            }
        }

        if (! $user) {
            if (! $email) {
                $email = sprintf('ya.%s.oauth@internal.localhost', $providerId);
                while (User::query()->where('email', $email)->exists()) {
                    $email = sprintf('ya.%s.%s.oauth@internal.localhost', $providerId, Str::lower(Str::random(6)));
                }
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'oauth_provider' => self::PROVIDER_YANDEX,
                'oauth_id' => $providerId,
                'role' => User::ROLE_CUSTOMER,
                'password' => null,
                'email_verified_at' => now(),
            ]);
        } else {
            if ($user->name === '' || $user->name === 'Покупатель') {
                $user->forceFill(['name' => $name])->save();
            }

            if ($email && $yandexUser->getEmail() && ! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        $plainToken = $user->createToken('spa')->plainTextToken;
        $code = Str::random(48);
        Cache::put('oauth_exchange:'.$code, [
            'token' => $plainToken,
            'user_id' => $user->id,
        ], now()->addMinutes(2));

        $url = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/')
            .'/auth/oauth-callback?code='.urlencode($code);

        return redirect()->away($url);
    }

    private function yandexConfigured(): bool
    {
        $id = (string) config('services.yandex.client_id', '');
        $secret = (string) config('services.yandex.client_secret', '');

        return $id !== '' && $secret !== '';
    }

    private function vkConfigured(): bool
    {
        $id = (string) config('services.vkontakte.client_id', '');
        $secret = (string) config('services.vkontakte.client_secret', '');

        return $id !== '' && $secret !== '';
    }

    private function oauthErrorRedirect(string $reason): RedirectResponse
    {
        $url = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/')
            .'/auth/oauth-callback?error='.urlencode($reason);

        return redirect()->away($url);
    }
}
