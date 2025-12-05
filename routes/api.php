<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Course\CourseBookingController;
use App\Http\Controllers\Course\CourseController;

Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    #Route::get('/courses', [CourseController::class,'index']);
    #Route::get('/courses/{course}', [CourseController::class,'show']);
    Route::post('/courses/{course}/book', [CourseBookingController::class,'store']);
    Route::apiResource('courses', CourseController::class);
});