<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Course\CourseSlot;
use App\Mail\CourseConfirmedMail;

use App\Actions\Course\CancelCourseSlotAction;
use App\Actions\Course\CancelCourseAction;

class CheckMinParticipants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public CourseSlot $slot)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CancelCourseSlotAction $cancelCourseSlotAction,CancelCourseAction $cancelCourseAction): void
    {
        DB::transaction(function () use ($cancelCourseSlotAction, $cancelCourseAction) {
            $slot = CourseSlot::lockForUpdate()->find($this->slot->id);

            if ($slot->status !== 'active') {
                return;
            }

            $count = $slot->bookedSlots()->count();

            if ($count < $slot->min_participants) {
                $reason = 'Mindestteilnehmerzahl von ' . $slot->min_participants . ' nicht erreicht';
                if($slot->course->booking_type === 'per_course'){
                    $cancelCourseAction->execute($slot,$reason);
                }else{
                    $cancelCourseSlotAction->execute($slot,$reason);
                }
                

            } else {
                $users = $slot->bookingSlots()
                    ->where('course_booking_slots.status', 'booked')
                    ->with('booking.user')
                    ->get()
                    ->map(fn($bs) => $bs->booking->user)
                    ->filter(fn($u) => $u && $u->email)
                    ->unique('id')
                    ->values();

                foreach ($users as $user) {
                    Mail::to($user->email)->queue(new CourseConfirmedMail($slot, $user));
                }
            }
        });
    }
}
