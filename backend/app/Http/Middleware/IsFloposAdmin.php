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
        if (config('app.edition') !== 'cloud') {
            abort(404);
        }

        $admin = auth('admin')->user();

        if (!$admin || !$admin->is_active) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Attach the admin user to the request for convenient access in controllers
        $request->attributes->set('admin', $admin);

        return $next($request);
    }
}
