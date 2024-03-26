<?php

namespace Ja\Stripe\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ja\Stripe\Support\StripeCustomer;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->singleton('stripe-customer', fn () => new StripeCustomer);
    }

    public function boot()
    {
        //
    }
}
