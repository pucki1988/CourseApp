<?php

namespace App\Policies;

use App\Models\Course\CourseSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CourseSlotPolicy
{
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function create(User $user, $course)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        // Coach darf Slot nur für eigene Kurse anlegen
        return $user->hasRole('coach') && $course->coach_id === $user->id;
    }

    public function update(User $user, CourseSlot $courseSlot)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return $user->hasRole('coach') && $courseSlot->course->coach_id === $user->id;
    }

    public function cancel(User $user, CourseSlot $slot)
    {
        // Manager darf nur absagen, wenn Mindestteilnehmerzahl noch nicht erreicht
        if ($user->hasRole('manager')) {
            return $slot->bookings()->count() < $slot->min_participants;
        }

        // Coach darf nur eigene Slots absagen, und nur wenn Mindestteilnehmerzahl noch nicht erreicht
        if ($user->hasRole('coach')) {
            return $slot->course->coach_id === $user->id
                && $slot->bookings()->count() < $slot->min_participants;
        }

        // Alle anderen dürfen nicht absagen
        return false;
    }

    public function delete(User $user, CourseSlot $slot)
    {
        if ($user->hasRole('manager')) {
            return $slot->bookings()->count() === 0;
        }

        return $user->hasRole('coach') 
            && $slot->course->coach_id === $user->id
            && $slot->bookings()->count() === 0;
    }
}
