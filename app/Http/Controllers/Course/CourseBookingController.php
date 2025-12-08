<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseBookingController extends Controller
{
    public function store(Request $request, Course $course)
    {
        if ($course->booking_type === 'all') {
            return $this->bookWholeCourse($course);
        }

        return $this->bookPerSlot($request, $course);
    }

    protected function bookWholeCourse(Course $course)
    {
        $confirmedCount = $course->bookings()->where('status','confirmed')->count();
        $status = ($course->capacity && $confirmedCount >= $course->capacity) ? 'waitlist' : 'confirmed';

        $booking = CourseBooking::create([
            'user_id' => auth()->id(),
            'course_id' => $course->id,
            'total_price' => $course->price,
            'status' => $status
        ]);

        // 3️⃣ Alle Slots anhängen – alle haben denselben Status
        $slotStatuses = [];
        foreach ($course->slots as $slot) {
            $slotStatuses[$slot->id] = ['status' => $status];
        }

        $booking->slots()->attach($slotStatuses);

        return response()->json([
            'message' => 'Buchung erfolgreich',
            'booking_status' => $status,
            'total_price' => $course->price,
            'slots' => $slotStatuses,
        ]);
    }

    

    protected function bookPerSlot(Request $request, Course $course)
    {
        $request->validate([
            'slots'=>['required','array'],
            'slots.*'=>[
                Rule::exists('course_slots','id')->where('course_id',$course->id)
            ]
        ]);

        $selectedSlots = CourseSlot::whereIn('id', $request->slots)->get();
        
        $slotStatuses = [];
        $totalPrice = 0;
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

        // 3️⃣ Gesamtstatus der Buchung ableiten
        if (collect($slotStatuses)->contains(fn($s) => $s['status'] === 'waitlist')) {
            $bookingStatus = 'waitlist';
        }else {
            $bookingStatus = 'confirmed';
        }

        // 4️⃣ Buchung erstellen
        $booking = CourseBooking::create([
            'user_id' => auth()->id(),
            'course_id' => $course->id,
            'total_price' => $totalPrice,
            'status' => $bookingStatus,
        ]);

        $booking->slots()->attach($slotStatuses);

        

        return response()->json([
            'message' => 'Buchung erfolgreich',
            'booking_status' => $bookingStatus,
            'total_price' => $totalPrice,
            'slots' => $slotStatuses,
        ]);
    }

    public function index(Request $request)
    {
        $query = CourseBooking::with(['slots']);
    
        $bookings = $query->get();

        return response()->json($bookings);
    }


    //Der Buchende sagt den Slot ab
    public function cancelSlot(CourseBooking $courseBooking, CourseSlot $courseSlot)
    {
        $this->authorize('cancelSlot', $courseBooking);
        $booking = CourseBooking::where('id', $courseBooking->id)
            ->where('user_id', operator: auth()->id())
            ->firstOrFail();

        // Pivot-Eintrag aktualisieren
        $booking->slots()->updateExistingPivot($courseSlot->id, [
            'status' => 'canceled'
        ]);

        // Automatisch Kursbuchung aktualisieren
        $this->refreshBookingStatus($booking);

        return response()->json([
            'message' => 'Slot wurde storniert',
            'booking_status' => $booking->status,
        ]);
    }

    protected function refreshBookingStatus(CourseBooking $booking)
    {
        $slots = $booking->slots;

        $activeSlots = $slots->filter(function ($slot) {
            return $slot->pivot->status !== 'canceled';
        });

        // Fall 1: Alles storniert → ganze Buchung stornieren
        if ($activeSlots->isEmpty()) {
            $booking->update(['status' => 'canceled']);
            return;
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