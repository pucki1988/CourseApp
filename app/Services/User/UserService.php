<?php
namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Events\UserRegistered;
use App\Events\MembershipConfirmed;
use Spatie\Permission\Models\Role;
use App\Models\Loyalty\LoyaltyAccount;

class UserService
{
    public function register(array $data): User
    {

        
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'member_requested' => $data['member_requested'] ?? false,
        ]);

        #$user->assignRole('user');


        $account = LoyaltyAccount::create(['type' => 'user']);
        $user->loyalty_account_id = $account->id;
        $user->save();

        event(new UserRegistered($user));

        return $user;
    }

    public function usersWithFrontendAccess(array $filters = [])
    {
        $perPage = (int) ($filters['per_page'] ?? 12);

        $users = User::with('roles')
            ->where(function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['member', 'user']);
                })
                ->whereDoesntHave('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'manager', 'course_manager', 'member_manager']);
                })
                ->orWhereDoesntHave('roles');
            });

        if (!empty($filters['username'])) {
            $users->where('name', 'like', '%' . $filters['username'] . '%');
        }


        return $users->paginate($perPage);
    }

    public function usersWithBackendAccess(array $filters = [])
    {
        $users = User::with('roles')
            ->where(function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'manager', 'course_manager', 'member_manager']);
                })
                ;
            });

        if (!empty($filters['username'])) {
            $users->where('name', 'like', '%' . $filters['username'] . '%');
        }


        return $users->get();
    }

    public function usersWithMemberRequest(){
        return User::where('member_requested', true)
        ->whereDoesntHave('members')
        ->get();
    }

    public function getAllRoles()
    {
        return Role::pluck('name');
    }

    public function disapproveMember(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'member_requested' => false,
        ]);
    }
}