<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course;
use App\Models\CourseBooking;
use App\Models\CourseSlot;
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

        return $this->bookPerDate($request, $course);
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

        $booking->slots()->attach($course->slots->pluck('id'));

        return response()->json([
            'message'=>'Buchung erfolgreich',
            'status'=>$status,
            'total_price'=>$course->price
        ]);
    }

    protected function bookPerDate(Request $request, Course $course)
    {
        $request->validate([
            'slots'=>['required','array'],
            'slots.*'=>[
                Rule::exists('course_slots','id')->where('course_id',$course->id)
            ]
        ]);

        $selectedSlots = CourseSlot::whereIn('id', $request->slots)->get();
        $fullSlots = [];

        foreach ($selectedSlots as $slot) {
            $confirmedCount = $slot->bookings()->where('status','confirmed')->count();
            if ($slot->capacity && $confirmedCount >= $slot->capacity) {
                $fullSlots[] = $slot->id;
            }
        }

        $status = count($fullSlots) > 0 ? 'waitlist' : 'confirmed';
        $totalPrice = $selectedSlots->sum('price');

        $booking = CourseBooking::create([
            'user_id'=>auth()->id(),
            'course_id'=>$course->id,
            'total_price'=>$totalPrice,
            'status'=>$status
        ]);

        $booking->slots()->attach($selectedSlots->pluck('id'));

        return response()->json([
            'message'=>'Buchung erfolgreich',
            'status'=>$status,
            'total_price'=>$totalPrice,
            'full_slots'=>$fullSlots
        ]);
    }
}