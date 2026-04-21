<?php

namespace App\Http\Controllers;

use App\Services\User\AppleWalletPassService;
use App\Services\User\GoogleWalletPassService;
use Illuminate\Http\Request;
use RuntimeException;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'is_member' => $request->user()->isMember(),
            'loyalty' => [ 
                'balance' => $request->user()->loyaltyAccount?->balance() ?? 0,
                'sport' => $request->user()->loyaltyAccount?->balanceByOrigin('sport') ?? 0,
                'event' => $request->user()->loyaltyAccount?->balanceByOrigin('course') ?? 0,
                'ticket' => $request->user()->loyaltyAccount?->balanceByOrigin('ticket') ?? 0,
                'work' => $request->user()->loyaltyAccount?->balanceByOrigin('work') ?? 0,
                'other' => $request->user()->loyaltyAccount?->balanceByOrigin('other') ?? 0,
                'point_value_eur' => (float) config('loyalty.point_value_eur', 0.01),
                'points_to_eur' => $request->user()->loyaltyAccount?->balance() * (float) config('loyalty.point_value_eur', 0.01) ?? 0,
            ],
            'wallet' => [
                'google' => $this->googleWalletPass($request, app(GoogleWalletPassService::class)),
                'apple' => ['download_url' => route('api.me.apple-wallet-pass')],
            ]
        ]);
    }

    public function qr_code(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'token' => $user->checkinToken?->token,
        ]);
    }

    public function googleWalletPass(Request $request, GoogleWalletPassService $walletPassService)
    {
        try {
            $status = $walletPassService->getObjectStatus($request->user());

            if ($status['exists']) {
                return [
                    'has_pass' => true,
                    'save_link' => 'https://pay.google.com/gp/v/object/'.rawurlencode($status['object_id']),
                    'object_id' => $status['object_id'],
                    'class_id' => $status['class_id'] ?? null,
                    'pass_type' => $status['pass_type'] ?? null,
                    'state' => $status['state'] ?? null,
                ];
            }
        } catch (RuntimeException $exception) {
            return [
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'has_pass' => false,
            'save_link' => $walletPassService->generateSaveLink($request->user()),
            'object_id' => $status['object_id'] ?? null,
            'class_id' => $status['class_id'] ?? null,
            'pass_type' => $request->user()->isMember() ? 'memberpass' : 'fitnesspass',
        ];
    }

    public function appleWalletPass(Request $request, AppleWalletPassService $appleWalletPassService)
    {
        try {
            $pkpassContent = $appleWalletPassService->generate($request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $filename = $request->user()->isMember() ? 'mitgliederpass.pkpass' : 'fitnesspass.pkpass';

        return response($pkpassContent, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function deleteGoogleWalletPass(Request $request, GoogleWalletPassService $walletPassService)
    {
        try {
            $result = $walletPassService->deactivateObject($request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function getGoogleWalletClass(Request $request, GoogleWalletPassService $walletPassService)
    {
        try {
            $result = $walletPassService->getClass($request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function patchGoogleWalletClass(Request $request, GoogleWalletPassService $walletPassService)
    {
        try {
            $result = $walletPassService->patchClass($request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function broadcastGoogleWalletMessage(Request $request, GoogleWalletPassService $walletPassService)
    {
        $validated = $request->validate([
            'header' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $walletPassService->broadcastMessage($validated['header'], $validated['body']);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function listGoogleWalletPassObjects(Request $request, GoogleWalletPassService $walletPassService)
    {
        $validated = $request->validate([
            'max_results' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'page_token' => ['nullable', 'string'],
        ]);

        try {
            $result = $walletPassService->listObjectsForClass(
                $request->user(),
                (int) ($validated['max_results'] ?? 20),
                $validated['page_token'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function updateGoogleWalletPass(Request $request, GoogleWalletPassService $walletPassService)
    {
        $validated = $request->validate([
            'state' => ['nullable', 'in:ACTIVE,INACTIVE,COMPLETED,EXPIRED'],
            'header' => ['nullable', 'string', 'max:120'],
            'subheader' => ['nullable', 'string', 'max:120'],
            'barcode_value' => ['nullable', 'string', 'max:255'],
            'barcode_alternate_text' => ['nullable', 'string', 'max:255'],
            'vereinsmitglied' => ['nullable', 'string', 'max:120'],
            'treuepunkte' => ['nullable', 'string', 'max:120'],
            'hex_background_color' => ['nullable', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ]);

        try {
            $result = $walletPassService->updateObject($request->user(), $validated);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function updateReceivesNews(Request $request)
    {
        $validated = $request->validate([
            'receives_news' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->receives_news = (bool) $validated['receives_news'];
        $user->save();

        return response()->json([
            'message' => 'News-Einstellung aktualisiert',
            'receives_news' => (bool) $user->receives_news,
            'user' => $user,
        ]);
    }
}