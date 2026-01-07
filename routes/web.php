<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');




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
    Volt::route('/courses', 'course_management.courses.index')->name('course_management.courses.index');
    Volt::route('/home', 'course_management.home.index')->name('course_management.home.index');

    Route::middleware('role:admin|manager')->group(function () {
              
        Volt::route('/courses/{course}', 'course_management.courses.show')->name('course_management.courses.show');
        Volt::route('/bookings', 'course_management.bookings.index')->name('course_management.bookings.index');
        Volt::route('/bookings/{booking}', 'course_management.bookings.show')->name('course_management.bookings.show');
    
    
        Volt::route('/coaches', 'course_management.coaches.index')->name('course_management.coaches.index');
        Volt::route('/coaches/{coach}', '.course_management.coaches.show')->name('course_management.coaches.show');

        Volt::route('/users', 'user_management.users.index')->name('user_management.users.index');
        Volt::route('/member_request', 'user_management.users.member_request')->name('user_management.users.member_request');
    });
   
});
