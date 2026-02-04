<?php

namespace App\Policies;

use App\Models\Course\CourseBooking;
use App\Models\Course\CourseBookingSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseBookingPolicy
{
    use HandlesAuthorization;

    /**
     * Vorab-Check: optional Admin-Zugriff
     */
    public function before(User $user, $ability)
    {
        // Admins dürfen alles
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        // Admin & Manager sehen alles
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return true;
        }

        // Berechtigung für das Anzeigen von Buchungen
        return $user->canany(['coursebookings.view','coursebookings.view.own']);
    }

    public function update(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancel(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancelBookingSlot(User $user, CourseBooking $booking,CourseBookingSlot $courseBookingSlot)
    {
        if($booking->booking_type==="per_course"){
            return false;
        }

        if (! $courseBookingSlot->slot) {
                return false; // Slot gelöscht → Sicherheit
        }

        if($courseBookingSlot->checked_in_at !== null){
            return false;
        }

        if(!$courseBookingSlot->slot->isInFuture()){
                return false;
        }

        if(!$courseBookingSlot->slot->minParticipantsReminderIsInFuture()){
            return false;
        }

        return $user->id === $booking->user_id;
    }

    public function cancelBooking(User $user, CourseBooking $booking)
    {
        // Slots laden (falls lazy)
        $booking->loadMissing('bookingSlots.slot');


        if(!$booking->isCancelable()){
            return false;
        }

        return $user->id === $booking->user_id;

        // 2️⃣ Alle Slots müssen Status "booked" haben
        /*$allBooked = $booking->bookingSlots->every(function ($bookingSlot) {
            return $bookingSlot->status === 'booked';
        });

        if (! $allBooked) {
            return false;
        }

        return $booking->bookingSlots->every(function ($bookingSlot) {

            if (! $bookingSlot->slot) {
                return false; // Slot gelöscht → Sicherheit
            }

            if(!$bookingSlot->slot->isInFuture()){
                return false;
            }

            if(!$bookingSlot->slot->minParticipantsReminderIsInFuture()){
                return false;
            }

            return true; 
        });*/
        
    }

}
