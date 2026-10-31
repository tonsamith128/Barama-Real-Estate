<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Billing\PaymentGateway;
use App\Services\Billing\StripeTestGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, StripeTestGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
