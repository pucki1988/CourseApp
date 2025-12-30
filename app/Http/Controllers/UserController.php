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
            'user' => $request->user()
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