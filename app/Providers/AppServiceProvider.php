<?php

namespace App\Providers;

use App\Services\CheckoutService;
use App\Services\Interfaces\CheckoutServiceInterface;
use App\Services\Interfaces\ProductServiceInterface;
use App\Services\Interfaces\WhatsappUserServiceInterface;
use App\Services\ProductService;
use App\Services\WhatsappUserService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            WhatsappUserServiceInterface::class,
            WhatsappUserService::class
        );

        $this->app->bind(
            ProductServiceInterface::class,
            ProductService::class
        );

        $this->app->bind(
            CheckoutServiceInterface::class,
            CheckoutService::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
