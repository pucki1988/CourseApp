<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Course\CourseBookingController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseSlotController;

Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Kurse
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    // Slots
    Route::get('/courses/{course}/slots', [CourseSlotController::class, 'index']);
    Route::get('/slots/{slot}', [CourseSlotController::class, 'show']);
    Route::post('/courses/{course}/slots', [CourseSlotController::class, 'store']);
    Route::put('/slots/{slot}', [CourseSlotController::class, 'update']);
    Route::delete('/slots/{slot}', [CourseSlotController::class, 'destroy']);
    Route::put('/slots/{slot}/reschedule', [CourseSlotController::class, 'reschedule']);
    Route::put('/slots/{slot}/cancel', [CourseSlotController::class, 'cancel']);


    Route::post('/booking/{course}', [CourseBookingController::class, 'store']);


});