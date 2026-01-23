<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Course\CourseBookingController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Course\CourseSlotController;
use App\Http\Controllers\Course\CourseBookingSlotController;
use App\Http\Controllers\Webhook\MollieWebhookController;

use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Password;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/me/qr-code', [UserController::class, 'qr_code']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Kurse
    
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses', [CourseController::class, 'store']);
    #Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    // Slots
    #Route::get('/courses/{course}/slots', [CourseSlotController::class, 'index']);
    Route::get('/slots/{slot}', [CourseSlotController::class, 'show']);
    Route::get('/slots', [CourseSlotController::class, 'index']);
    Route::post('/courses/{course}/slots', [CourseSlotController::class, 'store']);
    Route::put('/slots/{slot}', [CourseSlotController::class, 'update']);
    Route::delete('/slots/{slot}', [CourseSlotController::class, 'destroy']);
    Route::put('/slots/{courseSlot}/reschedule', [CourseSlotController::class, 'reschedule']);
    Route::put('/slots/{slot}/cancel', [CourseSlotController::class, 'cancel']);


    Route::post('/booking/{course}', [CourseBookingController::class, 'store']);
    Route::get('/bookings', [CourseBookingController::class, 'index']);
    Route::get('/bookings/{courseBooking}', [CourseBookingController::class, 'show']);
    Route::post('/booking/{courseBooking}/slots/{courseBookingSlot}/cancel', [CourseBookingController::class, 'cancelBookingSlot']);
    Route::post('/booking/{courseBooking}/cancel', [CourseBookingController::class, 'cancelCourseBooking']);
    Route::get('/booking_slots', [CourseBookingSlotController::class, 'index']);

    
});
Route::get('/status', function(){
    return "ok";
});

Route::post('/webhooks/mollie', MollieWebhookController::class)
    ->name('webhooks.mollie');
Route::get('/courses', [CourseController::class, 'index']);

Route::post('/feedback', [FeedbackController::class, 'send']);

Route::get('/settlement', [CourseBookingSlotController::class, 'settlement']);

Route::post('/password/forgot', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    return response()->json([
        'status' => __($status)
    ]);
});


Route::post('/password/reset', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed|min:8',
    ]);

    $status = Password::reset(
        $request->only(
            'email',
            'password',
            'password_confirmation',
            'token'
        ),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        }
    );

    return response()->json([
        'status' => __($status),
    ]);
});