<?php 

namespace App\Http\Controllers\Course;

use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingService;
use App\Services\Course\CourseCouponService;
use App\Models\Course\CourseSlot;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Contracts\PaymentService;
use App\Services\Bookings\BookingRefundService;
use App\Services\Bookings\BookingPaymentService;
use App\Actions\CourseBooking\CancelCourseBookingAction;
use App\Actions\CourseBooking\CreateBookingAction;
use App\Actions\CourseBooking\UserCancelBookingSlotAction;
use App\Exceptions\PaymentFailedException;
use App\Http\Resources\CourseBookingResource;


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
        try {
            $data = $action->execute($request,$course);
            return response()->json($data);
        }catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(), // 👈 HIER
            ], 422);
        }
    }

    public function validateCoupon(Request $request, Course $course, CourseCouponService $couponService)
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:255'],
            ...($course->booking_type === 'per_slot' ? [
                'slots' => ['required', 'array', 'min:1'],
                'slots.*' => ['required', 'integer', 'distinct'],
            ] : []),
        ]);

        $user = $request->user();
        if ($course->booking_type === 'per_course') {
            $prices = [$couponService->priceForCourse($course, $user)];
        } else {
            $slots = CourseSlot::where('course_id', $course->id)
                ->whereIn('id', $validated['slots'])
                ->where('status', 'active')
                ->get();

            if ($slots->count() !== count($validated['slots'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'slots' => 'Ein ausgewählter Termin ist nicht verfügbar oder gehört nicht zu diesem Kurs.',
                ]);
            }

            $prices = $couponService->pricesForSlots($course, $user, $slots);
        }

        $result = $couponService->preview($validated['coupon_code'], $user, $prices);

        return response()->json([
            'coupon_code' => $result['code'],
            'coupon_discount' => number_format($result['discount'] / 100, 2, '.', ''),
            'total_price' => number_format((array_sum($prices) - $result['discount']) / 100, 2, '.', ''),
        ]);
    }

    public function index()
    {
        $bookings = $this->courseBookingService->listBookingsFrontend();
        // Collection direkt zurückgeben, ohne "data" Wrapper
        return CourseBookingResource::collection($bookings)->resolve();
        
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