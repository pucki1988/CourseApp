<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

        return response()->json([
            'token' => $user->checkinToken?->token,
        ]);
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