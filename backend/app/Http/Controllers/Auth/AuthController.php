<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Main\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Middleware is now handled in routes file

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = auth('api')->user();

        // Check if user is active
        if (!$user->is_active) {
            auth('api')->logout();
            return response()->json(['error' => 'Account is inactive'], 403);
        }

        // Get user's tenants
        $tenants = $user->tenants()
            ->where('tenant_user.is_active', true)
            ->where('tenants.status', 'active')
            ->get(['tenants.id', 'tenants.business_name', 'tenants.slug', 'tenant_user.role']);

        return $this->respondWithToken($token, $user, $tenants);
    }

    /**
     * Register a new user and create a tenant.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:5',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|in:restaurant,salon,retail',
            'country' => 'required|in:IN,TH',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Auto-hashed by model
                'phone' => $request->phone,
                'country_code' => $request->country_code ?? '+91',
                'is_active' => true,
            ]);

            // Create tenant
            $slug = \Str::slug($request->business_name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure unique slug
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $tenant = Tenant::create([
                'owner_id' => $user->id,
                'business_name' => $request->business_name,
                'slug' => $slug,
                'database_name' => 'tenant_' . $slug,
                'business_type' => $request->business_type,
                'country' => $request->country,
                'currency' => $request->country === 'IN' ? 'INR' : 'THB',
                'timezone' => $request->country === 'IN' ? 'Asia/Kolkata' : 'Asia/Bangkok',
                'plan' => 'trial',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Attach user to tenant as owner
            $user->tenants()->attach($tenant->id, [
                'role' => 'owner',
                'is_active' => true,
            ]);

            // Provision tenant database
            $tenantService = app(TenantService::class);
            $tenantService->provisionDatabase($tenant);

            // Generate token
            $token = auth('api')->login($user);

            return response()->json([
                'message' => 'User and business registered successfully',
                'user' => $user,
                'tenant' => $tenant,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        $tenants = $user->tenants()
            ->where('tenant_user.is_active', true)
            ->where('tenants.status', 'active')
            ->get(['tenants.id', 'tenants.business_name', 'tenants.slug', 'tenant_user.role']);

        return response()->json([
            'user' => $user,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
            $user = auth('api')->user();
            $tenants = $user->tenants()
                ->where('tenant_user.is_active', true)
                ->where('tenants.status', 'active')
                ->get(['tenants.id', 'tenants.business_name', 'tenants.slug', 'tenant_user.role']);

            return $this->respondWithToken($token, $user, $tenants);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token refresh failed'], 401);
        }
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token, User $user, $tenants): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->full_phone,
                'is_flopos_admin' => (bool) $user->is_flopos_admin,
            ],
            'tenants' => $tenants,
        ]);
    }
}
