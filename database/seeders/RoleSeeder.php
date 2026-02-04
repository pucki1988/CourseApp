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
            'course_manager',
            'member_manager',
            'member',
            'user',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $permissions = [
            // Kurse (global)
            'courses.manage',
            'courses.create',
            'courses.view',
            'courses.update',
            'courses.delete',

            // Slots
            'courseslots.create',
            'courseslots.view',
            'courseslots.update',
            'courseslots.delete',
            'courseslots.cancel',
            'courseslots.reschedule',

            // Bookings
            'coursebookings.create',
            'coursebookings.view',
            'coursebookings.view.own',
            'coursebookings.update',
            'coursebookings.update.own',
            'coursebookings.manage',

            // Booking slots
            'coursebookingslots.view',
            'coursebookingslots.view.own',
            'coursebookingslots.update',
            'coursebookingslots.update.own',

            // Members 
            'members.create',
            'members.view',
            'members.view.own',
            'members.update',
            'members.update.own',
            'members.delete',
            'members.manage',
            
            // Users
            'users.view',
            'users.view.own',
            'users.update',
            'users.update.own',
            'users.manage',
            'users.view.requested_membership',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Delete permissions that are no longer in the list
        Permission::whereNotIn('name', $permissions)->delete();

        $admin = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'manager')->first();
        $courseManager = Role::where('name', 'course_manager')->first();
        $memberManager = Role::where('name', 'member_manager')->first();
        $member = Role::where('name', 'member')->first();
        $user = Role::where('name', 'user')->first();

        // Admin + Manager = alle Permissions
        $admin->syncPermissions(Permission::all());
        $manager->syncPermissions(Permission::all());

        // Course manager: manage courses, slots and related bookings
        $coursePerms = Permission::whereIn('name', [
            'courses.manage','courses.create','courses.view','courses.update','courses.update.own','courses.delete',
            'courseslots.create','courseslots.view','courseslots.update','courseslots.delete','courseslots.cancel','courseslots.reschedule',
            'coursebookings.view','coursebookings.update','coursebookings.manage',
            'coursebookingslots.view','coursebookingslots.update'
        ])->get();
        $courseManager->syncPermissions($coursePerms);

        // Member manager: full member management
        $memberManager->syncPermissions(Permission::whereIn('name', [
            'members.create','members.view','members.update','members.delete','members.manage'
        ])->get());

        // Member & User: frontend-only, only own resources
        $ownPerms = Permission::whereIn('name', [
            'members.view.own','members.update.own',
            'courses.view','coursebookings.view.own','coursebookings.update.own',
            'coursebookingslots.view.own','users.view.own','users.update.own',
        ])->get();

        $member->syncPermissions($ownPerms);
        $user->syncPermissions($ownPerms);

        
        
    }
}
