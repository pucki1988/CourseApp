<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentService;
use App\Services\Payments\MolliePaymentService;
use Illuminate\Auth\Notifications\ResetPassword;


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
        ResetPassword::createUrlUsing(function ($user, string $token) {
            if (request()->is('api/*')) {
                return config('app.frontend_url')
                    . '?reset-password&token=' . $token
                    . '&email=' . urlencode($user->email);
            }

            return config('app.url')
                . '/reset-password/' . $token
                . '?email=' . urlencode($user->email);
        });
    }
}
