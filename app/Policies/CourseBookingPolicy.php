<?php

namespace App\Policies;

use App\Models\Course\CourseBooking;
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

        // Normale User dürfen nur ihre eigenen Buchungen sehen (geregelt in Query)
        return true;
    }

    public function update(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancel(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancelBookingSlot(User $user, CourseBooking $booking)
    {
        if($booking->booking_type==="per_course"){
            return false;
        }
        return $user->id === $booking->user_id;
    }

    public function cancelBooking(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

}
