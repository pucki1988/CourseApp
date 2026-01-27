<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\CourseSlotCanceled;
use App\Listeners\SendCourseSlotCanceledMail;
use App\Events\CourseCanceled;
use App\Listeners\SendCourseCanceledMail;
use App\Listeners\SendCourseBookingSlotCanceledByUserMail;
use App\Events\CourseBookingSlotCanceledByUser;
use App\Listeners\SendCourseBookingCreateMail;
use App\Events\CourseBookingCreate;
use App\Listeners\SendWelcomeMail;
use App\Listeners\SendManagerNewUserMail;
use App\Events\UserRegistered;
use App\Listeners\SendCourseSlotRescheduleMail;
use App\Events\CourseSlotRescheduled;
use App\Listeners\SendMembershipConfirmedMail;
use App\Events\MembershipConfirmed;
use App\Listeners\SendCourseBookingPaidMail;
use App\Events\CourseBookingPaid;


class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Event => [ Listener(s) ]
        CourseSlotCanceled::class => [
            SendCourseSlotCanceledMail::class,
        ],
        CourseCanceled::class => [
            SendCourseCanceledMail::class,
        ],
        CourseBookingSlotCanceledByUser::class => [
            SendCourseBookingSlotCanceledByUserMail::class
        ],
        CourseSlotRescheduled::class => [
            SendCourseSlotRescheduleMail::class,
        ],
        CourseBookingCreate::class => [
            SendCourseBookingCreateMail::class
        ],
        UserRegistered::class => [
            SendWelcomeMail::class,
            SendManagerNewUserMail::class,
        ],
        MembershipConfirmed::class => [
            SendMembershipConfirmedMail::class
        ],
        CourseBookingPaid::class => [
            SendCourseBookingPaidMail::class
        ]
    ];

    public function boot()
    {
        parent::boot();
    }
}