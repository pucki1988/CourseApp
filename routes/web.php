<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\Course\CheckinController;

Route::redirect('/', '/login')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/checkin/qr/{user}', [CheckinController::class, 'handle'])
    ->name('qr.checkin')
    ->middleware('signed');




Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

     
    
    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
    
    

    Route::middleware('permission:courses.manage')->group(function () {
        Volt::route('/home', 'course_management.home.index')->name('course_management.home.index');
        Volt::route('/courses', 'course_management.courses.index')->name('course_management.courses.index');
        Volt::route('/courses/{course}', 'course_management.courses.show')->name('course_management.courses.show');
        Volt::route('/course-settings/sport-types', 'course_management.settings.sport-types')->name('course_management.settings.sport-types');
        Volt::route('/course-settings/equipment-items', 'course_management.settings.equipment-items')->name('course_management.settings.equipment-items');
        Volt::route('/settlement', 'course_management.settlement.index')->name('course_management.settlement.index');
    });

    Route::middleware(['permission:coaches.manage','permission:coaches.view'])->group(function () {
        Volt::route('/coaches', 'course_management.coaches.index')->name('course_management.coaches.index');
        Volt::route('/coaches/{coach}', '.course_management.coaches.show')->name('course_management.coaches.show');
    });

    Route::middleware(['permission:coursebookings.manage','permission:coursebookings.view'])->group(function () {
        Volt::route('/bookings', 'course_management.bookings.index')->name('course_management.bookings.index');
        Volt::route('/bookings/{booking}', 'course_management.bookings.show')->name('course_management.bookings.show');
    });
    
    Route::middleware(['permission:members.manage','permission:members.view'])->group(function () {
       Volt::route('/members', 'member_management.members.index')->name('member_management.members.index');
    });

    Route::middleware(['permission:users.manage','permission:users.view'])->group(function () {
        Volt::route('/users', 'user_management.users.index')->name('user_management.users.index');
        Volt::route('/users/{user}', 'user_management.users.show')->name('user_management.users.show');
        Volt::route('/backend_user', 'user_management.users.backend_user')->name('user_management.users.backend_user');
    });

    Route::middleware(['permission:users.manage','permission:users.view.requested_membership'])->group(function () {
        Volt::route('/member_request', 'user_management.users.member_request')->name('user_management.users.member_request');
    });
   
});
