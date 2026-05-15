<?php

namespace App\Actions\CourseBooking;


use App\Models\Course\CourseBookingSlot;
use App\Models\Course\Course;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseBookingService;
use App\Contracts\PaymentService;
use Illuminate\Http\Request;
use LogicException;

class CreateBookingAction
{
    public function __construct(
        protected CourseBookingService $courseBookingService,
        protected PaymentService $paymentService,
    ) {}
    public function execute(Request $request, Course $course): array
    {
        return DB::transaction(function () use ($request, $course) {

            $newBooking = $this->courseBookingService->store($request, $course);

            if ($newBooking->payment()->exists()) {
                throw new LogicException('Für diese Buchung existiert bereits ein Payment.');
            }

            #Lokalen Payment-Record anlegen, dann an Provider übergeben
            
            $localPayment = $newBooking->payment()->create([
                'amount'   => $newBooking->total_price,
                'currency' => 'EUR',
                'method'   => 'pending',
                'provider' => 'mollie',
                'status'   => 'pending',
            ]);

            $newBooking->load('payment');

            $this->paymentService->createPayment($localPayment);
            $this->courseBookingService->refreshBookingStatus($newBooking);

            $data["booking"]=$newBooking->refresh();
            return $data;
        });
    }
}
