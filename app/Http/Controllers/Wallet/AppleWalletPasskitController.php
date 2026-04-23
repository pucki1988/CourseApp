<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\AppleWalletDevice;
use App\Models\User;
use App\Services\User\AppleWalletPassService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AppleWalletPasskitController extends Controller
{
    public function __construct(private AppleWalletPassService $appleWalletPassService)
    {
    }

    public function registerDevice(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier,
        string $serialNumber
    ) {

        Log::info('Wallet Register Device', [
            'device' => $deviceLibraryIdentifier,
            'pass' => $passTypeIdentifier,
            'serial' => $serialNumber,
            'pushToken' => $request->input('pushToken'),
        ]);
        
        if (!$this->isAuthorized($request, $serialNumber)) {
            return response()->json([], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isPassTypeAllowed($passTypeIdentifier)) {
            return response()->json([], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'pushToken' => ['required', 'string', 'max:255'],
        ]);

        $existing = AppleWalletDevice::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->first();

        AppleWalletDevice::query()->updateOrCreate(
            [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ],
            [
                'push_token' => (string) $request->string('pushToken'),
                'auth_token' => $this->appleWalletPassService->authenticationTokenForSerial($serialNumber),
            ]
        );

        return response()->json([], $existing ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function unregisterDevice(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier,
        string $serialNumber
    ) {
        if (!$this->isAuthorized($request, $serialNumber)) {
            return response()->json([], Response::HTTP_UNAUTHORIZED);
        }

        AppleWalletDevice::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->delete();

        return response()->json([], Response::HTTP_OK);
    }

    public function listUpdatedSerialNumbers(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier
    ) {
        $since = $request->query('passesUpdatedSince');
        $sinceTs = is_string($since) && $since !== '' ? (int) $since : null;

        $query = AppleWalletDevice::query()
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier);

        if ($sinceTs) {
            $query->where('updated_at', '>', now()->setTimestamp($sinceTs));
        }

        $rows = $query->orderBy('updated_at')->get(['serial_number', 'updated_at']);

        if ($rows->isEmpty()) {
            return response()->json([], Response::HTTP_NO_CONTENT);
        }

        $lastUpdated = (string) $rows->max(fn ($row) => $row->updated_at?->getTimestamp() ?? 0);

        return response()->json([
            'lastUpdated' => $lastUpdated,
            'serialNumbers' => $rows->pluck('serial_number')->values()->all(),
        ]);
    }

    public function latestPass(
        Request $request,
        string $passTypeIdentifier,
        string $serialNumber
    ) {
        if (!$this->isAuthorized($request, $serialNumber)) {
            return response()->json([], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isPassTypeAllowed($passTypeIdentifier)) {
            return response()->json([], Response::HTTP_NOT_FOUND);
        }

        $userId = $this->appleWalletPassService->extractUserIdFromSerialNumber($serialNumber);

        if (!$userId) {
            return response()->json([], Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($userId);

        if (!$user) {
            return response()->json([], Response::HTTP_NOT_FOUND);
        }

        $pkpass = $this->appleWalletPassService->generate($user);

        return response($pkpass, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="djk-wallet-pass.pkpass"',
        ]);
    }

    private function isAuthorized(Request $request, string $serialNumber): bool
    {
        return $this->appleWalletPassService->hasValidAuthenticationToken($request, $serialNumber);
    }

    private function isPassTypeAllowed(string $passTypeIdentifier): bool
    {
        return $passTypeIdentifier === config('services.apple_wallet.pass_type_identifier');
    }
}
