<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseSlot;
use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Bookings\BookingPaymentService;

class CourseBookingController extends Controller
{

    public function __construct(
        protected CourseBookingService $service,
        protected PaymentService $paymentService,
        protected BookingRefundService $bookingRefundService,
        protected BookingPaymentService $bookingPaymentService,
    ) {}

    public function store(Request $request, Course $course)
    {
        $data["booking"] = $this->service->store($request, $course);

        $data["payment"] = $this->paymentService->createPayment($data["booking"]);

        $this->bookingPaymentService->setTransactionId($data["booking"],$data["payment"]->transactionId);

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
        $refund= $this->paymentService->refund($courseBooking,$courseSlot->price);
        $this->bookingRefundService->createRefund($courseBooking,$courseSlot->price,$refund);

        $data = $this->service->cancelSlot($courseBooking, $courseSlot);




        return response()->json($data);
    }

    
}