<?php

namespace App\Listeners;

use App\Events\MembershipConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\MembershipConfirmedMail;

class SendMembershipConfirmedMail implements ShouldQueue
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
    public function handle(MembershipConfirmed $event): void
    {
        Mail::to($event->user->email)->send(
            new MembershipConfirmedMail($event->user)
        );
    }
}
