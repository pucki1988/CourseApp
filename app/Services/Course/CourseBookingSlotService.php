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
        $courseBookingSlot->update(['status' => 'canceled']);
        return [
            'message'        => 'Booking Slot wurde abgesagt'
        ];
    }

    public function refund(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'refunded']);
        return [
            'message'        => 'Booking Slot wurde zurückerstattet'
        ];
    }

    public function refund_failed(CourseBookingSlot $courseBookingSlot)
    {
        $courseBookingSlot->update(['status' => 'refund_failed']);
        return [
            'message'        => 'Rückerstattung fehlgeschlagen'
        ];
    }

    public function listBookedSlots(array $filters = [])
    {
        $query = CourseSlot::query()
    ->with(['course', 'bookingSlots.booking'])

    // 🔹 Nur zukünftige / laufende Slots
    ->where(function ($q) {
        $q->whereDate('date', '>', now())
          ->orWhere(function ($q2) {
              $q2->whereDate('date', now())
                 ->whereTime('start_time', '>=', now()->format('H:i'));
          });
    })

    // 🔹 Slot muss mind. eine gültige Buchung haben
    ->whereHas('bookingSlots.booking', function ($q) {
        $q->whereIn('status', ['paid', 'partially_refunded']);
    })

    // 🔹 Mind. ein Slot ist noch nicht eingecheckt
    ->whereHas('bookingSlots', function ($q) {
        $q->where('status', 'booked')
          ->whereNull('checked_in_at');
    })

    ->orderBy('date')
    ->orderBy('start_time');
       
        $user=auth()->user();

        if ($user->hasAnyRole(['user', 'member'])) {
            $query->whereHas('booking', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->get();
    }

}