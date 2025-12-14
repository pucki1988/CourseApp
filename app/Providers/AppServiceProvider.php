<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentService;
use App\Services\Payments\MolliePaymentService;


class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        
        
    ];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentService::class, MolliePaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
    }
}
