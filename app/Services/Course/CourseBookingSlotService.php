<?php

namespace App\Services\Course;

use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBookingSlot;
use App\Services\Loyalty\LoyaltyPointService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseBookingSlotService
{
    public function __construct(
        private LoyaltyPointService $loyaltyPointService
    ) {
    }

    public function cancel(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'canceled']);
        
    }

    public function refund(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'refunded']);
    }

    public function refund_failed(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'refund_failed']);
        
    }

    public function checkIn(CourseBookingSlot $bookingSlot): void
    {
        if ($bookingSlot->checked_in_at !== null) {
            return; // idempotent
        }
        DB::transaction(function () use ($bookingSlot) {
            $lockedSlot = CourseBookingSlot::whereKey($bookingSlot->id)->lockForUpdate()->first();

            if ($lockedSlot->checked_in_at !== null) {
                return;
            }

            $lockedSlot->update([
                'checked_in_at' => now()
            ]);

            $this->loyaltyPointService->earn(
                $lockedSlot->booking->user->loyaltyAccount,
                1,
                'earn',
                'sport',
                $lockedSlot,
                'Sportkurs'
            );
        });
    }

    public function listBookedSlots(array $filters = [])
    {
        //Die gebuchten Slots des jeweiligen User
        $query = CourseBookingSlot::with(['slot.course', 'booking'])
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
                ->whereHas('booking', function ($p0) {
                    $p0->whereIn('status',['paid','partially_refunded']);
                })
                ->orderBy(
                    CourseSlot::select('date')
                        ->whereColumn('course_slots.id', 'course_booking_slots.course_slot_id')
                )
                ->orderBy(
                    CourseSlot::select('start_time')
                        ->whereColumn('course_slots.id', 'course_booking_slots.course_slot_id')
                );
       
        $user = Auth::user();

        $query->whereHas('booking', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
        

        return $query->get();
    }

}