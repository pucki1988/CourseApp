<?php

namespace App\Policies;

use App\Models\Course\CourseSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Carbon\Carbon;

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
        
        if($user->hasRole('coach') && $courseSlot->course->coach_id === $user->id && $courseSlot->bookedSlots()->count() === 0){
            #return true;
        }
    }

    public function reschedule(User $user, CourseSlot $courseSlot)
    {
        if ($user->hasRole('manager')) {
            return $courseSlot->isCancelable();
        }
        return $user->hasRole('coach') && $courseSlot->course->coach_id === $user->id && $courseSlot->isCancelable();
    }

    public function cancel(User $user, CourseSlot $slot)
    {
            //  Kurse mit Gesamtbuchung nie einzeln stornierbar
            if ($slot->course->booking_type === 'per_course') {
                return false;
            }

            //  Slot grundsätzlich nicht stornierbar
            if (! $slot->isCancelable()) {
                return false;
            }

            //  Manager
            if ($user->hasRole('manager')) {
                return $this->minParticipantsNotReached($slot);
            }

            //  Coach
            if ($user->hasRole('coach')) {
                return $slot->course->coach_id === $user->id
                    && $this->minParticipantsNotReached($slot);
            }

            return false;
    }

    public function delete(User $user, CourseSlot $slot)
    {
        if ($user->hasRole('manager')) {
            #return $slot->bookedSlots()->count() === 0;
        }
        return false;
    }

    protected function minParticipantsNotReached(CourseSlot $slot): bool
    {
        return $slot->bookedSlots()
            ->count() < $slot->min_participants;
    }
}
