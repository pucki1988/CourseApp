<?php

namespace App\Services\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseSlot;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Actions\CourseBooking\UserCancelBookingSlotAction;

class CourseBookingService
{

    public function __construct(
        
    ) {}
    /**
     * Buchung starten – entscheidet zwischen WholeCourse und PerSlot
     */
    public function store(Request $request, Course $course)
    {
        if ($course->booking_type === 'per_course') {
            return $this->bookWholeCourse($course);
        }

        return $this->bookPerSlot($request, $course);
    }

    public function cancelBookingSlot(CourseBooking $courseBooking,CourseBookingSlot $courseBookingSlot){
        
        $action = app(UserCancelBookingSlotAction::class);
        $action->execute($courseBooking,$courseBookingSlot);
    }

    /**
     * Einzelne Buchung mit Relationen laden
     */
    public function loadBooking(CourseBooking $courseBooking)
    {
        if (! auth()->user()->hasAnyRole('admin', 'manager')
        && $courseBooking->user_id !== auth()->id()
        ) {
            abort(403);
        }
        
        return $courseBooking->load([
        'course',
        'course.slots' => function ($q) {
            $q->orderBy('date')
              ->orderBy('start_time');
        },
        'bookingSlots',
        ]);
    }


    /**
     * Ganze Kursbuchung
     */
    public function bookWholeCourse(Course $course)
    {
        $bookingCount = $course->bookings()
            ->where('payment_status', 'paid')
            ->count();

        if($bookingCount >= $course->capacity)
        {
            return;
        }

            $user=auth('sanctum')->user();
            $isMember = $user && $user->hasRole('member');
            
            $discount=0;
            if($isMember){
                $discount=$course->member_discount;
            }

        

        $booking = CourseBooking::create([
            'user_id'     => auth()->id(),
            'user_name' => $user->name,
            'course_id'   => $course->id,
            'course_title' => $course->title,
            'total_price' => $course->price - $discount,
            'booking_type' => $course->booking_type
        ]);

        foreach ($course->slots as $slot) {

            if ($slot->status !== 'active') {
                continue; // überspringe inaktive Slots
            }

            $booking->bookingSlots()->create([
                'course_slot_id' => $slot->id,
                'price'          => 0
            ]);
        }

        return $booking;
    }


    /**
     * Slot-basierte Buchung
     */
    public function bookPerSlot(Request $request, Course $course): mixed
    {

        return DB::transaction(function () use ($request, $course): CourseBooking {
            $selectedSlots = CourseSlot::whereIn('id', $request->slots)->where('status','active')
            ->lockForUpdate()
            ->get();

            if($selectedSlots->count() === 0){
                throw new \Exception("Kein Slot ausgewählt");
            }

            $createBookingSlots = [];
            $totalPrice   = 0;
            $user=auth('sanctum')->user();
            $isMember = $user && $user->hasRole('member');
            
            $discount=0;
            if($isMember){
                $discount=$course->member_discount;
            }

            foreach ($selectedSlots as $slot) {
                $bookedSlotsCount = $slot->bookingSlots()
                    ->where('status', 'booked')
                    ->count();

                if ($bookedSlotsCount >= $slot->capacity) {
                    throw new \Exception("Slot {$slot->id} ist ausgebucht");
                }

                $createBookingSlots[] = $slot;
                $totalPrice += ($slot->price - $discount);  
            }

            $booking = CourseBooking::create([
                'user_id'     => auth()->id(),
                'user_name' => $user->name,
                'course_id'   => $course->id,
                'course_title' => $course->title,
                'total_price' => $totalPrice,
                'booking_type' => $course->booking_type
            ]);

            foreach ($createBookingSlots as $slot) {

                if ($slot->status !== 'active') {
                    continue; // überspringe inaktive Slots
                }
                $booking->bookingSlots()->create([
                    'course_slot_id' => $slot->id,
                    'price'          => ($slot->price - $discount),
                ]);
            }
            return $booking;
        });

    }


    /**
     * Liste der Buchungen
     */
    public function listBookings(array $filters = [])
    {
        $query=CourseBooking::with(['course','bookingSlots.slot']);

        if (!auth()->user()->hasAnyRole('admin','manager')) {
        // Normale User sehen nur eigene
            $query->where('user_id', auth()->user()->id);
        }

         $query->whereIn('payment_status',['pending','open','paid']);

        if (!empty($filters['status'])) {
            $query->where('status',$filters['status']);
        }

        if (!empty($filters['bookingId'])) {
            $query->where('id',$filters['bookingId']);
        }



        $query->orderByDesc('created_at');


        return $query->get();
    }

    /**
     * Automatische Status-Neuberechnung
     */
    public function refreshBookingStatus(CourseBooking $booking)
    {
        if ($booking->payment_status !== 'paid') {
            $booking->update(['status' => 'pending']);
            return;
        }

        $totalSlots = $booking->bookingSlots()->count();
        $refundedSlots = $booking->bookingSlots()
            ->where('status', 'refunded')
            ->count();

        if ($refundedSlots === 0) {
            $booking->update(['status' => 'paid']);
            return;
        }

        if ($refundedSlots < $totalSlots) {
            $booking->update(['status' => 'partially_refunded']);
            return;
        }

            $booking->update(['status' => 'refunded']);
        }
}