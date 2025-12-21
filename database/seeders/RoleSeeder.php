<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin',
            'manager',
            'member',
            'user',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

         $permissions = [
            // Kurse
            'courses.create',
            'courses.update',
            'courses.update.own',
            'courses.delete',
            'courses.delete.own',

            // Slots
            'courseslots.create',
            'courseslots.create.own',
            'courseslots.update',
            'courseslots.update.own',
            'courseslots.delete',
            'courseslots.delete.own',
            'courseslots.cancel',
            'courseslots.cancel.own',

        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'manager')->first();
        
        // Admin + Manager = alle Permissions
        $admin->syncPermissions(Permission::all());
        $manager->syncPermissions(Permission::all());

        
        
    }
}
