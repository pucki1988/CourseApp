<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendManagerNewUserMail implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        // Admin-Mail-Adresse (Config empfohlen!)
        $adminEmail = env("MANAGER_MAIL") ?? "aschuster.development@outlook.de";

        Mail::to($adminEmail)->send(
            new ManagerNewUserMail($user)
        );
    }
}
