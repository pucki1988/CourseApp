<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseBookingController extends Controller
{

    public function __construct(
        protected CourseBookingService $service
    ) {}

    public function store(Request $request, Course $course)
    {
        $data = $this->service->store($request, $course);
        return response()->json($data);
    }

    public function index()
    {
        return response()->json(
            $this->service->listBookings()
        );
    }
    //Der Buchende sagt den gebuchten Slot ab.
    public function cancelSlot(CourseBooking $courseBooking, CourseSlot $courseSlot)
    {
        $this->authorize('cancelSlot', $courseBooking);

        $data = $this->service->cancelSlot($courseBooking, $courseSlot);

        return response()->json($data);
    }

    
}