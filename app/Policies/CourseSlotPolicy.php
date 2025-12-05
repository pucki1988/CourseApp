<?php

namespace App\Policies;

use App\Models\CourseSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CourseSlotPolicy
{
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return true;
        }
    }

    public function create(User $user, $course)
    {
        // Coach darf Slot nur für eigene Kurse anlegen
        return $user->hasRole('coach') && $course->coach_id === $user->id;
    }

    public function update(User $user, CourseSlot $slot)
    {
        return $user->hasRole('coach') && $slot->course->coach_id === $user->id;
    }

    public function delete(User $user, CourseSlot $slot)
    {
        return $user->hasRole('coach') && $slot->course->coach_id === $user->id;
    }
}
