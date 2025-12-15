<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseBookingSlotService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Bookings\BookingPaymentService;
use App\Actions\CourseBooking\CancelBookingSlotAction;
use App\Actions\CourseBooking\CreateBookingAction;

class CourseBookingController extends Controller
{

    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected CourseBookingSlotService $courseBookingSlotService,
        protected PaymentService $paymentService,
        protected BookingRefundService $bookingRefundService,
        protected BookingPaymentService $bookingPaymentService,
    ) {}

    public function store(Request $request, Course $course,CreateBookingAction $action)
    {
        $data = $action->execute($request,$course);
        return response()->json($data);
    }

    public function index()
    {
        return response()->json(
            $this->courseBookingService->listBookings()
        );
    }
    //Der Buchende sagt den gebuchten Slot ab.
    public function cancelSlot(CourseBooking $courseBooking, CourseBookingSlot $courseBookingSlot,CancelBookingSlotAction $action)
    {
        $this->authorize('cancelSlot', $courseBooking);
        $slot = $action->execute($courseBooking, $courseBookingSlot);
        return response()->json($slot);
    }

    
}