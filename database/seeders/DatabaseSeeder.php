<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user=User::firstOrCreate(
            ['email' => 'schuster-andi@gmx.de'],
            [
                'name' => 'Andreas Schuster',
                'password' => '12345678',
                'email_verified_at' => now(),
            ]
        );

        $user1=User::firstOrCreate(
            ['email' => 'user@test.de'],
            [
                'name' => 'User Test',
                'password' => '12345678',
                'email_verified_at' => now(),
            ]
        );

        $this->call(RoleSeeder::class);

        $user->assignRole('admin');

        $user1->assignRole('user');
    }
}
