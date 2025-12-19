<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\CourseSlotCanceled;
use App\Listeners\SendCourseSlotCanceledMail;
use App\Listeners\SendCourseBookingSlotCanceledByUserMail;
use App\Events\CourseBookingSlotCanceledByUser;
use App\Listeners\SendCourseBookingCreateMail;
use App\Events\CourseBookingCreate;
use App\Listeners\SendWelcomeMail;
use App\Events\UserRegistered;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Event => [ Listener(s) ]
        CourseSlotCanceled::class => [
            SendCourseSlotCanceledMail::class,
        ],
        CourseBookingSlotCanceledByUser::class => [
            SendCourseBookingSlotCanceledByUserMail::class
        ],
        CourseBookingCreate::class => [
            SendCourseBookingCreateMail::class
        ],
        UserRegistered::class => [
            SendWelcomeMail::class,
        ],
    ];

    public function boot()
    {
        parent::boot();
    }
}