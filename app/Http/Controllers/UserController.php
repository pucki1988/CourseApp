<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'loyalty' => [ 
                'balance' => $request->user()->loyaltyAccount?->balance() ?? 0,
                'sport' => $request->user()->loyaltyAccount?->balanceByOrigin('sport') ?? 0,
                'event' => $request->user()->loyaltyAccount?->balanceByOrigin('course') ?? 0,
                'ticket' => $request->user()->loyaltyAccount?->balanceByOrigin('ticket') ?? 0,
                'work' => $request->user()->loyaltyAccount?->balanceByOrigin('work') ?? 0,
                'other' => $request->user()->loyaltyAccount?->balanceByOrigin('other') ?? 0,
                'point_value_eur' => (float) config('loyalty.point_value_eur', 0.01),
                'points_to_eur' => $request->user()->loyaltyAccount?->balance() * (float) config('loyalty.point_value_eur', 0.01) ?? 0,
            ]
        ]);
    }

    public function qr_code(Request $request)
    {
        $user = $request->user();

        $signedUrl = URL::temporarySignedRoute(
            'qr.checkin',
            now()->addMinutes(5),
            ['user' => $user->id]
        );

        // 🔹 QR Renderer (SVG)
        /*$renderer = new ImageRenderer(
            new RendererStyle(300),
            new GdImageBackEnd()
        );

        $writer = new Writer($renderer);
        $qrPng = 'data:image/png;base64,' . base64_encode($writer->writeString($signedUrl));*/

        return response()->json([
            'qr_url' => $signedUrl
        ]);
    }
}