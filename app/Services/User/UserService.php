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

        $user->assignRole('user');


        $account = LoyaltyAccount::create(['type' => 'user']);
        $user->loyalty_account_id = $account->id;
        $user->save();

        event(new UserRegistered($user));

        return $user;
    }

    public function usersWithFrontendAccess(array $filters = [])
    {
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


        return $users->get();
    }

    public function usersWithBackendAccess(array $filters = [])
    {
        $users = User::with('roles')
            ->where(function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'manager', 'course_manager', 'member_manager']);
                })
                ->orWhereDoesntHave('roles');
            });

        if (!empty($filters['username'])) {
            $users->where('name', 'like', '%' . $filters['username'] . '%');
        }


        return $users->get();
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

        if($user->hasAnyRole(['manager','admin']))
        {
            if($user->hasRole('user')){
                $user->removeRole('user');
                $user->assignRole('member');
            }else{
                $user->assignRole('member');
            }
        }else{
            $user->syncRoles(['member']);

            if($user->member_requested){
                event(new MembershipConfirmed($user));
            }

            $user->update([
                'member_requested' => false,
            ]);
        }
        
    }

    public function approveManager(int $userId): void
    {
        $user = User::findOrFail($userId);

        // alte Rolle raus, neue rein
        if($user->hasAnyRole(['manager','admin']))
        {
            return;
        }
        
        $user->assignRole('manager');
    }

    public function unsetManager(int $userId): void
    {
        $user = User::findOrFail($userId);

        if($user->hasAnyRole(['manager']))
        {
            $user->removeRole('manager');
            
        }
    }

    public function disapproveMember(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'member_requested' => false,
        ]);
    }

    public function unsetMember(int $userId): void
    {
        $user = User::findOrFail($userId);

        if($user->hasAnyRole(['manager','admin']))
        {
            $user->removeRole('member');
            $user->assignRole('user');
        }else{
            // alte Rolle raus, neue rein
            $user->syncRoles(['user']);
            $user->update([
                'member_requested' => false,
            ]);
        }
    }




}