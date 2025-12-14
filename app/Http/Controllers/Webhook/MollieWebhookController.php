<?php
namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Contracts\PaymentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MollieWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentService $paymentService
    ) {
        // Mollie sendet payment ID als "id"
        $paymentId = $request->input('id');
        
        if (!$paymentId) {
            return response('Missing payment id', Response::HTTP_BAD_REQUEST);
        }

        // Payment beim Provider prüfen & Booking aktualisieren
        $paymentService->handleWebhook($paymentId);

        // Mollie erwartet nur 200 OK
        return response()->noContent();
    }
}
