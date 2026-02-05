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
        
    }

    public function create(User $user, $course)
    {
        if ($user->hasRole(['manager','admin'])) {
            return true;
        }
        
        return false;
    }

    public function update(User $user, CourseSlot $courseSlot)
    {
        if ($user->hasAnyPermission(['courseslots.update'])) {
            return true;
        }

    }

    public function reschedule(User $user, CourseSlot $courseSlot)
    {
        if ($user->hasAnyPermission(['courseslots.reschedule'])) {
            return $courseSlot->isCancelable();
        }
    }

    public function checkin(User $user, CourseSlot $courseSlot)
    {
        if ($user->hasAnyPermission(['courseslots.checkin'])) {
            return true;
        }
        return false;
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
            if ($user->hasAnyPermission(['courseslots.cancel'])) {
                return $this->minParticipantsNotReached($slot);
            }

            return false;
    }

    public function delete(User $user, CourseSlot $slot)
    {
        return false;
    }

    protected function minParticipantsNotReached(CourseSlot $slot): bool
    {
        return $slot->bookedSlots()
            ->count() < $slot->min_participants;
    }
}
