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
    public function handle(CancelCourseSlotAction $cancelAction): void
    {
        
            $slot = CourseSlot::lockForUpdate()->find($this->slot->id);

            if ($slot->status !== 'active') {
                return;
            }

            $count = $slot->bookedSlots()->count();

            if ($count < $slot->min_participants) {
                $cancelAction->execute($slot,'Mindestteilnehmerzahl von '. $slot->min_participants.' nicht erreicht');

            } /*else {
                Mail::to(
                    $slot->bookings->pluck('email')
                )->send(new CourseConfirmedMail($slot));
            }*/
        
    }
}
