<?php
namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Events\UserRegistered;
use Spatie\Permission\Models\Role;

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

        $user->assignRole('user');

        event(new UserRegistered($user));

        return $user;
    }

    public function usersWithFrontendAccess()
    {
        $users=User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['user', 'member']);
            })
            ->orWhereDoesntHave('roles')
            ->with('roles')
            ->get();
        return $users;
    }

    public function usersWithMemberRequest(){
        return User::where('member_requested', true)
        ->role('user') // noch keine member
        ->get();
    }

    public function getAllRoles()
    {
        return Role::pluck('name');
    }

    

    public function approveMember(int $userId): void
    {
        $user = User::findOrFail($userId);

        // alte Rolle raus, neue rein
        $user->syncRoles(['member']);

        $user->update([
            'member_requested' => false,
        ]);
    }

    public function disapproveMember(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'member_requested' => false,
        ]);
    }

}