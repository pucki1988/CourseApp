<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingService;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Bookings\BookingPaymentService;
use App\Actions\CourseBooking\CancelCourseBookingAction;
use App\Actions\CourseBooking\CreateBookingAction;
use App\Actions\CourseBooking\UserCancelBookingSlotAction;
use App\Exceptions\PaymentFailedException;

class CourseBookingController extends Controller
{

    public function __construct(
        protected CourseBookingService $courseBookingService,
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
        $bookings = $this->courseBookingService->listBookings();

        return response()->json(
            $bookings->map(function ($booking) {

                return array_merge(
                    $booking->toArray(),
                    [
                        'booking_slots' => $booking->bookingSlots->map(function ($bookingSlot) {

                            return array_merge(
                                $bookingSlot->toArray(),
                                [
                                    'slot' => array_merge(
                                        $bookingSlot->slot->toArray(),
                                        [
                                            // 👇 DAS ist der Fix
                                            'date' => $bookingSlot->slot->date->toDateString(),
                                        ]
                                    ),
                                ]
                            );
                        }),
                    ]
                );

            })
        );
    }

    public function show(CourseBooking $courseBooking)
    {
        return response()->json(
            $this->courseBookingService->loadBooking($courseBooking)
        );
    }

    //Der Buchende sagt den gebuchten Slot ab.
    public function cancelBookingSlot(CourseBooking $courseBooking, CourseBookingSlot $courseBookingSlot,UserCancelBookingSlotAction $action)
    {
        $this->authorize('cancelBookingSlot', [$courseBooking, $courseBookingSlot]);

        try{
            $slot = $action->execute($courseBooking, $courseBookingSlot);
            return response()->json($slot);
        }catch (PaymentFailedException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], status: 422);
        }
        
    }

    public function cancelCourseBooking(CourseBooking $courseBooking,CancelCourseBookingAction $action){
        
        $this->authorize('cancelBooking', $courseBooking);
        try{
            $booking = $action->execute($courseBooking);
            return response()->json($booking);
        }catch (PaymentFailedException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], status: 422);
        }
    }
        
    

    
}