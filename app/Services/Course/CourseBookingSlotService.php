<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBookingSlot;
use App\Services\Loyalty\LoyaltyPointService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
       
        $user=auth()->user();

        $query->whereHas('booking', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
        

        return $query->get();
    }

    public function loadSettlements()
    {
        $slots = CourseSlot::with(['course.coach.compensationTiers'])
        #->whereDate('date', '<', today()) // nur Slots in der Vergangenheit
        ->whereHas('bookingSlots', fn ($q) =>
        $q->where('status', 'booked')
        )
        ->withCount([
            'bookingSlots as bookings_count' => fn ($q) =>
                $q->where('status', 'booked')
        ])
        ->orderByDesc('id')
        ->get()
        ->map(function ($slot) {
            $slot->revenue =  $slot->bookingSlots->where('status', 'booked')->sum('price') ?? 0;
            $slot->checked_in_users= $slot->bookingSlots->where('status', 'booked')->whereNotNull('checked_in_at')->count() ?? 0;
            
            // Calculate coach compensation
            $participantCount = $slot->bookings_count;
            $coach = $slot->course?->coach;
            
            if ($coach && $coach->compensationTiers) {
                $tier = $coach->compensationTiers
                    ->where('min_participants', '<=', $participantCount)
                    ->where('max_participants', '>=', $participantCount)
                    ->first();
                    
                $slot->coach_compensation = $tier?->compensation;
            } else {
                $slot->coach_compensation = null;
            }
            
            return $slot;
        });

        return $slots;
    }

}