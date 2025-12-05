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
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return true;
        }
    }

    public function create(User $user)
    {
        return $user->hasRole('coach');
    }

    public function update(User $user, Course $course)
    {
        return $user->hasRole('coach') && $course->coach_id === $user->id;
    }

    public function delete(User $user, Course $course)
    {
        return $user->hasRole('coach') && $course->coach_id === $user->id;
    }
}
