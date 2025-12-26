<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course\Course;
use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;

class CheckinController extends Controller
{
    public function handle(Request $request, User $user)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Ungültiger QR-Code');
        }

        // Trainer wählt vorher den Slot
        $slot = CourseSlot::findOrFail($request->slot_id);

        // 1️⃣ Gehört User zur Organisation?
        abort_if(
            $user->organization_id !== auth()->user()->organization_id,
            403
        );

        // 2️⃣ Hat User gültige Buchung?
        $bookingSlot = CourseBookingSlot::query()
            ->where('user_id', $user->id)
            ->where('course_slot_id', $slot->id)
            ->where('status', 'booked')
            ->first();

        abort_if(! $bookingSlot, 403, 'Keine gültige Buchung');

        // 3️⃣ Check-in
        $bookingSlot->update([
            'checked_in_at' => now(),
            'status' => 'checked_in',
        ]);

        return response()->json([
            'message' => 'Check-in erfolgreich'
        ]);
    }

}