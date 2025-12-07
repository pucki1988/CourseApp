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

    public function view(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function update(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancel(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }

    public function cancelSlot(User $user, CourseBooking $booking)
    {
        return $user->id === $booking->user_id;
    }
}
