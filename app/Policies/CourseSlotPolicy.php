<?php

namespace App\Policies;

use App\Models\CourseSlot;
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

    public function update(User $user, CourseSlot $slot)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return $user->hasRole('coach') && $slot->course->coach_id === $user->id;
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
