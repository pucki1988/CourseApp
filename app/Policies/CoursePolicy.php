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
        if ($user->hasRole('manager')) {
            return true;
        }
        return $user->hasRole('coach');
    }

    public function create(User $user)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return $user->hasRole('coach');
    }

    public function update(User $user, Course $course)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return $user->hasRole('coach') && $course->coach_id === $user->id;
    }

    public function delete(User $user, Course $course)
    {
        if ($user->hasRole('manager')) {
            // Prüfen: gibt es Buchungen auf Slots?
            foreach ($course->slots as $slot) {
                if ($slot->bookings()->count() > 0) {
                    return false; // Kurs darf nicht gelöscht werden
                }
            }

            return true;
        }
        
        
        if ($user->hasRole('coach')) {
            // Coach darf nur eigene Kurse löschen
            if ($course->coach_id !== $user->id) {
                return false;
            }

            // Prüfen: gibt es Buchungen auf Slots?
            foreach ($course->slots as $slot) {
                if ($slot->bookings()->count() > 0) {
                    return false; // Kurs darf nicht gelöscht werden
                }
            }

            return true;
        }

        return false; // andere Rollen dürfen nicht löschen
    }
}
