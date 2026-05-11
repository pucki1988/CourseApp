<?php

namespace App\Actions\CourseBooking;


use App\Models\Course\CourseBookingSlot;
use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseBookingService;
use App\Services\Bookings\BookingPaymentService;
use App\Contracts\PaymentService;
use Illuminate\Http\Request;
use App\Events\CourseBookingCreate;
use App\Models\Payment\Payment;

class CreateBookingAction
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected PaymentService $paymentService,
        protected BookingPaymentService $bookingPaymentService,
    ) {}
    public function execute(Request $request, Course $course): array
    {
        return DB::transaction(function () use ($request, $course) {

            $newBooking = $this->courseBookingService->store($request, $course);

            // Lokalen Payment-Record anlegen, dann an Provider übergeben
            $localPayment = $newBooking->payments()->create([
                'amount'   => $newBooking->total_price,
                'currency' => 'EUR',
                'method'   => 'pending',
                'provider' => 'mollie',
                'status'   => 'draft',
            ]);

            $data["payment"] = $this->paymentService->createPayment($localPayment);

            $this->bookingPaymentService->setPaymentData($newBooking,$data["payment"]->transactionId,$data["payment"]->checkoutUrl);
            $this->courseBookingService->refreshBookingStatus($newBooking);

            $data["booking"]=$newBooking->refresh();
        
           /* DB::afterCommit(fn () =>
                event(new CourseBookingCreate(
                    $newBooking
                ))
            );*/

            return $data;
        });
    }
}