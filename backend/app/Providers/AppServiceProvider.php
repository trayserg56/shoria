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
        //
    }
}
