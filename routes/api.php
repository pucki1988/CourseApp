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
use App\Http\Controllers\News\NewsController;

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Wallet\AppleWalletPasskitController;
use Illuminate\Support\Facades\Password;

Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/me/qr-code', [UserController::class, 'qr_code']);
    Route::get('/me/google-wallet-pass', [UserController::class, 'googleWalletPass']);
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
    Route::post('/push/subscriptions/unsubscribe', [PushSubscriptionController::class, 'destroy']);
    Route::post('/push/subscriptions/test', [PushSubscriptionController::class, 'sendTest']);
    /*Route::get('/me/google-wallet-pass/objects', [UserController::class, 'listGoogleWalletPassObjects']);
    Route::get('/me/google-wallet-pass/class', [UserController::class, 'getGoogleWalletClass']);
    Route::post('/me/google-wallet-pass', [UserController::class, 'updateGoogleWalletPass']);
    Route::post('/me/google-wallet-pass/deactivate', [UserController::class, 'deleteGoogleWalletPass']);
    Route::post('/me/google-wallet-pass/class/patch', [UserController::class, 'patchGoogleWalletClass']);
    Route::post('/me/google-wallet-pass/broadcast', [UserController::class, 'broadcastGoogleWalletMessage']);*/

    Route::post('/me/receives-news', [UserController::class, 'updateReceivesNews']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::prefix('wallet')->group(function () {
    Route::get('/apple/pass/{userId}', [UserController::class, 'appleWalletPassSigned'])
    ->middleware('signed')
    ->name('api.apple-wallet-pass');

    //Apple API Endpoints
    Route::prefix('apple/v1')->group(function () {
        Route::post(
            '/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}',
            [AppleWalletPasskitController::class, 'registerDevice']
        );

        Route::delete(
            '/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}',
            [AppleWalletPasskitController::class, 'unregisterDevice']
        );

        Route::get(
            '/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}',
            [AppleWalletPasskitController::class, 'listUpdatedSerialNumbers']
        );

        Route::get(
            '/passes/{passTypeIdentifier}/{serialNumber}',
            [AppleWalletPasskitController::class, 'latestPass']
        );
    });

    
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
Route::get('/news', [NewsController::class, 'index']);

Route::post('/feedback', [FeedbackController::class, 'send']);

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