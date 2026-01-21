<?php
namespace App\Services\Member;

use App\Models\Member\Member;
use App\Models\User;

class MemberService
{
    public function getMembers(array $filters = [])
    {
        // Admin sieht alle
        if (auth()->user()->hasAnyRole('admin', 'manager')) {
            $members= Member::with('user')->orderBy('last_name')->orderBy('entry_date');
        }

        if (!empty($filters['name'])) {
            $name = $filters['name'];
            $members->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$name}%"]);
        }

        return $members->get();
    }
}