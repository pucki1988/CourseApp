<?php
namespace App\Services\Member;

use App\Models\Member\Member;
use App\Models\User;

class MemberService
{
    public function getMembers(array $filters = [])
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $members = Member::with('user')
            ->orderBy('last_name')
            ->orderBy('entry_date');

        // Berechtigungen: view = alle (Priorität), view.own = nur eigene
        if ($user->can('members.view')) {
            // alle anzeigen
        } elseif ($user->can('members.view.own')) {
            $members->where('user_id', $user->id);
        } else {
            return collect();
        }

        if (!empty($filters['name'])) {
            $name = $filters['name'];
            $members->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$name}%"]);
        }

        return $members->get();
    }
}