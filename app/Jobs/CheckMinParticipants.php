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
                

            } /*else {
                Mail::to(
                    $slot->bookings->pluck('email')
                )->send(new CourseConfirmedMail($slot));
            }*/
        });
    }
}
