<?php

namespace App\Policies;

use App\Models\Course\Coach;
use App\Models\User;
use Illuminate\Auth\Access\Response;


class CoachPolicy
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
        return true;
    }

    public function create(User $user)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return false;
    }

    public function update(User $user)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        return false;
    }

    public function delete(User $user)
    {
        if ($user->hasRole('manager')) {
            return true;
        }
        
        return false; // andere Rollen dürfen nicht löschen
    }
}
