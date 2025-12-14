<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBooking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseBookingService
{
    /**
     * Buchung starten – entscheidet zwischen WholeCourse und PerSlot
     */
    public function store(Request $request, Course $course)
    {
        if ($course->booking_type === 'all') {
            return $this->bookWholeCourse($course);
        }

        return $this->bookPerSlot($request, $course);
    }


    /**
     * Ganze Kursbuchung
     */
    public function bookWholeCourse(Course $course)
    {
        $confirmedCount = $course->bookings()
            ->where('status','confirmed')
            ->count();

        $status = ($course->capacity && $confirmedCount >= $course->capacity)
            ? 'waitlist'
            : 'confirmed';

        $booking = CourseBooking::create([
            'user_id'     => auth()->id(),
            'course_id'   => $course->id,
            'total_price' => $course->price,
            'status'      => $status
        ]);

        // Alle Slots anhängen
        $slotStatuses = [];
        foreach ($course->slots as $slot) {
            $slotStatuses[$slot->id] = ['status' => $status];
        }

        $booking->slots()->attach($slotStatuses);

        return $booking;
    }


    /**
     * Slot-basierte Buchung
     */
    public function bookPerSlot(Request $request, Course $course)
    {
        $request->validate([
            'slots' => ['required', 'array'],
            'slots.*' => [
                Rule::exists('course_slots', 'id')->where('course_id', $course->id)
            ]
        ]);

        $selectedSlots = CourseSlot::whereIn('id', $request->slots)->get();

        $slotStatuses = [];
        $totalPrice   = 0;

        foreach ($selectedSlots as $slot) {
            $confirmedCount = $slot->bookings()
                ->where('course_booking_slots.status', 'confirmed')
                ->count();

            $slotStatus = ($slot->capacity && $confirmedCount >= $slot->capacity)
                ? 'waitlist'
                : 'confirmed';

            $slotStatuses[$slot->id] = ['status' => $slotStatus];
            $totalPrice += $slot->price;
        }

        // Gesamtstatus ableiten
        $bookingStatus = collect($slotStatuses)
            ->contains(fn($s) => $s['status'] === 'waitlist')
            ? 'waitlist'
            : 'confirmed';

        $booking = CourseBooking::create([
            'user_id'     => auth()->id(),
            'course_id'   => $course->id,
            'total_price' => $totalPrice,
            'status'      => $bookingStatus
        ]);

        $booking->slots()->attach($slotStatuses);

        return $booking;
    }


    /**
     * Liste der Buchungen
     */
    public function listBookings(array $filters = [])
    {
        $query=CourseBooking::with(['slots','course']);

        if (!auth()->user()->hasAnyRole('admin','manager')) {
        // Normale User sehen nur eigene
            $query->where('user_id', auth()->user()->id);
        }

        if (!empty($filters['status'])) {
            $query->where('status',$filters['status']);
        }


        return $query->get();
    }


    /**
     * Slot-Stornierung
     */
    public function cancelSlot(CourseBooking $courseBooking, CourseSlot $courseSlot)
    {
        $booking = CourseBooking::where('id', $courseBooking->id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $booking->slots()->updateExistingPivot($courseSlot->id, [
            'status' => 'canceled'
        ]);

        $this->refreshBookingStatus($booking);

        return [
            'message'        => 'Slot wurde storniert',
            'booking_status' => $booking->status
        ];
    }


    /**
     * Automatische Status-Neuberechnung
     */
    public function refreshBookingStatus(CourseBooking $booking)
    {
        $slots = $booking->slots;

        $activeSlots = $slots->filter(fn($slot) =>
            $slot->pivot->status !== 'canceled'
        );

        if ($activeSlots->isEmpty()) {
            return $booking->update(['status' => 'canceled']);
        }

        $confirmed = $activeSlots->filter(fn($s) => $s->pivot->status === 'confirmed')->count();
        $waitlist  = $activeSlots->filter(fn($s) => $s->pivot->status === 'waitlist')->count();

        if ($waitlist > 0) {
            $booking->update(['status' => 'waitlist']);
        } elseif ($confirmed === $activeSlots->count()) {
            $booking->update(['status' => 'confirmed']);
        } else {
            $booking->update(['status' => 'partial']);
        }
    }
}