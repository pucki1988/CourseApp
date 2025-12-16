<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\CourseSlotCanceled;
use App\Listeners\SendCourseSlotCanceledMail;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Event => [ Listener(s) ]
        CourseSlotCanceled::class => [
            SendCourseSlotCanceledMail::class,
        ],
    ];

    public function boot()
    {
        parent::boot();
    }
}