<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PaymentService;
use App\Services\Payments\MolliePaymentService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Symfony\Component\Mime\Address;

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

        View::addNamespace('layouts', resource_path('views/components/layouts'));

        /*if ($bcc = config('mail.bcc_all_to')) {
            Event::listen(MessageSending::class, function (MessageSending $event) use ($bcc) {
                $event->message->addBcc(new Address($bcc));
            });
        }*/
    }
}
