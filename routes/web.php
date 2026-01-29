<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\Course\CheckinController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/checkin/qr/{user}', [CheckinController::class, 'handle'])
    ->name('qr.checkin')
    ->middleware('signed');




Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

     Volt::route('/settlement', 'course_management.settlement.index')->name('course_management.settlement.index');
    
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
    Volt::route('/courses', 'course_management.courses.index')->name('course_management.courses.index');
    Volt::route('/home', 'course_management.home.index')->name('course_management.home.index');

    Route::middleware('role:admin|manager')->group(function () {
              
        Volt::route('/courses/{course}', 'course_management.courses.show')->name('course_management.courses.show');
        Volt::route('/bookings', 'course_management.bookings.index')->name('course_management.bookings.index');
        Volt::route('/bookings/{booking}', 'course_management.bookings.show')->name('course_management.bookings.show');

        Volt::route('/coaches', 'course_management.coaches.index')->name('course_management.coaches.index');
        Volt::route('/coaches/{coach}', '.course_management.coaches.show')->name('course_management.coaches.show');

        Volt::route('/course-settings/sport-types', 'course_management.settings.sport-types')->name('course_management.settings.sport-types');
        Volt::route('/course-settings/equipment-items', 'course_management.settings.equipment-items')->name('course_management.settings.equipment-items');

        Volt::route('/users', 'user_management.users.index')->name('user_management.users.index');
        Volt::route('/backend_user', 'user_management.users.backend_user')->name('user_management.users.backend_user');
        Volt::route('/member_request', 'user_management.users.member_request')->name('user_management.users.member_request');
    
        Volt::route('/members', 'member_management.members.index')->name('member_management.members.index');
    
       
    });
   
});
