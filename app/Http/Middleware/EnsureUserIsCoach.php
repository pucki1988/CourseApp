<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCoach
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->coach) {
            abort(403);
        }

        return $next($request);
    }
}
