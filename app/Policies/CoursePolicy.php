<?php

namespace App\Policies;

use App\Models\Course\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    /**
     * Vorab-Check für Admin/Manager
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('admin')) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        if ($user->hasAnyPermission(permissions: ['courses.view'])) {
            return true;
        }
        return true;
    }

    public function create(User $user)
    {
        if ($user->hasAnyPermission(['courses.create'])) {
            return true;
        }
        return false;
    }

    public function update(User $user, Course $course)
    {
        if ($user->hasAnyPermission(['courses.update'])) {
            return true;
        }
        return false;
    }

    public function delete(User $user, Course $course)
    {
        if ($user->hasAnyPermission(['courses.delete'])) {
            // Prüfen: gibt es Buchungen auf Slots?
            foreach ($course->slots as $slot) {
                if ($slot->bookedSlots->count() > 0) {
                    return false; // Kurs darf nicht gelöscht werden
                }
            }

            return true;
        }
        
        return false; // andere Rollen dürfen nicht löschen
    }
}
