<?php
namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Events\UserRegistered;


class UserService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('user');

        event(new UserRegistered($user));

        return $user;
    }
}