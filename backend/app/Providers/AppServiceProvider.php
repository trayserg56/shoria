<?php

namespace App\Providers;

use App\Support\Delivery\Contracts\DeliveryGateway;
use App\Support\Delivery\DeliveryGatewayRegistry;
use App\Support\Delivery\Gateways\CdekDeliveryGateway;
use App\Support\Delivery\Gateways\ManualDeliveryGateway;
use App\Support\Payments\Contracts\PaymentGateway;
use App\Support\Payments\Gateways\ManualCashPaymentGateway;
use App\Support\Payments\Gateways\SberPaymentGateway;
use App\Support\Payments\Gateways\TBankPaymentGateway;
use App\Support\Payments\PaymentGatewayRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayRegistry::class, function () {
            return new PaymentGatewayRegistry([
                $this->app->make(TBankPaymentGateway::class),
                $this->app->make(SberPaymentGateway::class),
                $this->app->make(ManualCashPaymentGateway::class),
            ]);
        });

        $this->app->singleton(DeliveryGatewayRegistry::class, function () {
            return new DeliveryGatewayRegistry([
                $this->app->make(ManualDeliveryGateway::class),
                $this->app->make(CdekDeliveryGateway::class),
            ]);
        });

        $this->app->bind(PaymentGateway::class, TBankPaymentGateway::class);
        $this->app->bind(DeliveryGateway::class, ManualDeliveryGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-api', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_PUBLIC_API', 180))
                ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute((int) env('RATE_LIMIT_AUTH_LOGIN', 10))
                ->by($request->ip().'|'.$email);
        });

        RateLimiter::for('auth-register', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_AUTH_REGISTER', 5))
                ->by($request->ip());
        });

        RateLimiter::for('auth-recovery', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute((int) env('RATE_LIMIT_AUTH_RECOVERY', 6))
                ->by($request->ip().'|'.$email);
        });

        RateLimiter::for('events', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_EVENTS', 240))
                ->by($request->ip().'|'.(string) $request->header('X-Session-Id', 'na'));
        });

        RateLimiter::for('newsletter', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_NEWSLETTER', 12))
                ->by($request->ip());
        });

        RateLimiter::for('cart-write', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_CART_WRITE', 120))
                ->by($request->ip().'|'.(string) $request->input('session_id', 'na'));
        });

        RateLimiter::for('checkout-write', function (Request $request): Limit {
            return Limit::perMinute((int) env('RATE_LIMIT_CHECKOUT_WRITE', 30))
                ->by($request->ip().'|'.(string) $request->input('customer_email', 'na'));
        });

        RateLimiter::for('checkout-preview', function (Request $request): Limit {
            $sessionId = (string) $request->input('session_id', 'na');
            $userId = (string) ($request->user()?->id ?? 'guest');

            return Limit::perMinute((int) env('RATE_LIMIT_CHECKOUT_PREVIEW', 180))
                ->by($request->ip().'|'.$userId.'|'.$sessionId);
        });

        RateLimiter::for('webhooks', function (Request $request): Limit {
            $providerCode = (string) $request->route('providerCode', 'unknown');

            return Limit::perMinute((int) env('RATE_LIMIT_WEBHOOKS', 240))
                ->by($request->ip().'|'.$providerCode);
        });
    }
}
