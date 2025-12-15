<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CourseBookingSlotService
{
    
    public function cancel(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'refunded']);
        return [
            'message'        => 'Booking Slot wurde storniert'
        ];
    }


    public function listBookedSlots(array $filters = [])
    {
        $query = CourseBookingSlot::with(['slot', 'booking'])
                ->whereHas('slot', function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereDate('date', '>', now())
                        ->orWhere(function ($q3) {
                            $q3->whereDate('date', now())
                                ->whereTime('start_time', '>=', now()->format('H:i'));
                        });
                    });
                })
                ->where('status','booked')
                ->orderBy(
                    CourseSlot::select('date')
                        ->whereColumn('course_slots.id', 'course_booking_slots.course_slot_id')
                )
                ->orderBy(
                    CourseSlot::select('start_time')
                        ->whereColumn('course_slots.id', 'course_booking_slots.course_slot_id')
                );
       
        $user=auth()->user();

        if ($user->hasAnyRole(['user', 'member'])) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->get();
    }

}