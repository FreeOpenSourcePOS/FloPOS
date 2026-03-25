<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Login as a Flopos admin.
     * POST /api/admin/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $token = auth('admin')->attempt([
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if (!$token) {
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        $user = auth('admin')->user();

        if (!$user->is_active) {
            auth('admin')->logout();
            return response()->json(['error' => 'Account is deactivated.'], 403);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated admin user.
     * GET /api/admin/auth/me
     */
    public function me(): JsonResponse
    {
        return response()->json(['user' => auth('admin')->user()]);
    }

    /**
     * Logout the admin.
     * POST /api/admin/auth/logout
     */
    public function logout(): JsonResponse
    {
        auth('admin')->logout();
        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Refresh the admin JWT token.
     * POST /api/admin/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth('admin')->refresh());
    }

    /**
     * Change the authenticated admin's password.
     * POST /api/admin/auth/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('admin')->user();

        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => $request->password]);

        return response()->json(['message' => 'Password updated.']);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('admin')->factory()->getTTL() * 60,
            'user'         => auth('admin')->user(),
        ]);
    }
}
