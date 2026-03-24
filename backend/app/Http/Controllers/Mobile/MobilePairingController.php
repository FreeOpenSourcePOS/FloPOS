<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class MobilePairingController extends Controller
{
    /**
     * Get the current user's pairing code (or null if not set).
     * GET /api/mobile/pairing-code
     */
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'pairing_code' => $user->mobile_pairing_code,
            'rotated_at' => $user->mobile_pairing_code_rotated_at?->toIso8601String(),
        ]);
    }

    /**
     * Generate or rotate the pairing code.
     * POST /api/mobile/rotate-code
     */
    public function rotate(): JsonResponse
    {
        $user = auth('api')->user();

        $code = strtoupper(Str::random(6));

        // Ensure uniqueness
        while (User::where('mobile_pairing_code', $code)->exists()) {
            $code = strtoupper(Str::random(6));
        }

        $user->update([
            'mobile_pairing_code' => $code,
            'mobile_pairing_code_rotated_at' => now(),
        ]);

        return response()->json([
            'pairing_code' => $code,
            'rotated_at' => now()->toIso8601String(),
            'message' => 'Pairing code generated. Enter this code in the Flo mobile app.',
        ]);
    }

    /**
     * Pair a mobile device using a 6-character code.
     * POST /api/mobile/pair
     * Body: { "code": "A3B7K2" }
     * Returns a long-lived read-only JWT with scope: reports
     */
    public function pair(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $code = strtoupper($request->input('code'));

        $user = User::where('mobile_pairing_code', $code)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'Invalid pairing code. Please check the code and try again.',
            ], 404);
        }

        // Get all active tenants for this user
        $tenants = $user->tenants()
            ->where('tenant_user.is_active', true)
            ->where('tenants.status', 'active')
            ->get(['tenants.id', 'tenants.business_name', 'tenants.slug', 'tenants.currency', 'tenants.timezone', 'tenant_user.role']);

        $tenantIds = $tenants->pluck('id')->toArray();

        // Issue a long-lived read-only token (30 days) with scope: reports
        $customClaims = [
            'scope' => 'reports',
            'user_id' => $user->id,
            'user_name' => $user->name,
            'tenant_ids' => $tenantIds,
        ];

        // Use a longer TTL for mobile tokens (43200 minutes = 30 days)
        $token = JWTAuth::customClaims($customClaims)
            ->fromUser($user);

        return response()->json([
            'message' => 'Paired successfully.',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 43200 * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'tenants' => $tenants->map(fn($t) => [
                'id' => $t->id,
                'business_name' => $t->business_name,
                'slug' => $t->slug,
                'currency' => $t->currency,
                'timezone' => $t->timezone,
                'role' => $t->pivot->role,
            ]),
        ]);
    }
}
