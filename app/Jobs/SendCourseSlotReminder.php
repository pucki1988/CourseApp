<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseSlotReminder;
use App\Mail\CourseSlotReminderMail;

class SendCourseSlotReminder implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public CourseSlot $slot,
        public CourseSlotReminder $reminder)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $emails = $this->slot->bookedSlots()
            ->join('course_bookings', 'course_booking_slots.course_booking_id', '=', 'course_bookings.id')
            ->join('users', 'course_bookings.user_id', '=', 'users.id')
            ->pluck('users.email')
            ->unique()
            ->values()
            ->toArray();

        if (empty($emails)) {
            return;
        }

        Mail::to($emails)
            ->send(new CourseSlotReminderMail(
                $this->slot,
            ));
    }
}
