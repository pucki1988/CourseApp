<?php

namespace App\Http\Controllers;

use App\Notifications\TestWebPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        $publicKey = (string) config('webpush.vapid.public_key');

        if ($publicKey === '') {
            return response()->json([
                'message' => 'Web Push ist nicht konfiguriert.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'publicKey' => $publicKey,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm',
        );

        $request->user()->update(['receives_push' => true]);
        
        $request->user()->notify(new TestWebPushNotification());

        return response()->json([], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);
        $request->user()->update(['receives_push' => false]);

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions()->doesntExist()) {
            return response()->json([
                'message' => 'Keine aktiven Push-Abonnements gefunden.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->notify(new TestWebPushNotification);

        return response()->json([], Response::HTTP_ACCEPTED);
    }
}