<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsFloposAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Admin panel does not exist on self-hosted instances.
        // This is a second layer of defence — routes are not even registered
        // when APP_EDITION != 'cloud', but this guard ensures the same even
        // if code paths change in future.
        if (config('app.edition') !== 'cloud') {
            abort(404);
        }

        $user = auth('api')->user();

        if (!$user || !$user->is_flopos_admin) {
            return response()->json([
                'error' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
