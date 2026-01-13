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
use App\Actions\CourseBooking\UserCancelBookingSlotAction;
use App\Actions\CourseBooking\CreateBookingAction;
use App\Http\Resources\CourseBookingSlotResource;

class CourseBookingSlotController extends Controller
{

    public function __construct(
        protected CourseBookingSlotService $courseBookingSlotService
    ) {}

    public function index()
    {
        $slots = $this->courseBookingSlotService->listBookedSlots();

        return CourseBookingSlotResource::collection($slots);
    }
   

    
}