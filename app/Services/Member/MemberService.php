<?php
namespace App\Services\Member;

use App\Models\Member\Member;

class MemberService
{
    public function getMembers(array $filters = [])
    {
        $perPage = (int) ($filters['per_page'] ?? 12);

        $user = auth()->user();
        if (!$user) {
            return Member::query()->whereRaw('1 = 0')->paginate($perPage);
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
            return Member::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        if (!empty($filters['name'])) {
            $name = $filters['name'];
            $members->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$name}%"]);
        }

        // Filter für ausgetretene Mitglieder
        if (isset($filters['show_exited'])) {
            if ($filters['show_exited'] === 'active') {
                $members->whereNull('left_at');
            } elseif ($filters['show_exited'] === 'exited') {
                $members->whereNotNull('left_at');
            }
            // 'all' zeigt alle an (kein zusätzlicher Filter)
        }

        return $members->paginate($perPage);
    }
}