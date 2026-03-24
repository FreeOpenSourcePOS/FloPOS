<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionEnforcement
{
    public function handle(Request $request, Closure $next): Response
    {
        // Self-hosted edition bypasses subscription checks
        if (config('app.edition') === 'self-hosted') {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return $next($request);
        }

        // Allow if on an active trial
        if ($tenant->isOnTrial()) {
            return $next($request);
        }

        // Allow if has an active subscription
        if ($tenant->subscription && $tenant->subscription->isActive()) {
            return $next($request);
        }

        // Trial expired and no active subscription
        if ($tenant->trialExpired()) {
            return response()->json([
                'error' => 'subscription_required',
                'message' => 'Your trial has expired. Please subscribe to continue using Flo POS.',
                'trial_ended_at' => $tenant->trial_ends_at?->toIso8601String(),
            ], 402);
        }

        // Suspended or cancelled
        if ($tenant->status === 'suspended') {
            return response()->json([
                'error' => 'account_suspended',
                'message' => 'Your account has been suspended. Please contact support.',
                'reason' => $tenant->suspension_reason,
            ], 402);
        }

        return $next($request);
    }
}
