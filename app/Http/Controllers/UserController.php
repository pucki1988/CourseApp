<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
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
        $renderer = new ImageRenderer(
            new RendererStyle(250),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($signedUrl);

        return response()->json([
            'qr_url' => $signedUrl,
            'qr_svg' => $qrSvg,
        ]);
    }
}