<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Get user's tenants.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        $tenants = $user->tenants()
            ->where('tenant_user.is_active', true)
            ->where('tenants.status', 'active')
            ->get(['tenants.*', 'tenant_user.role']);

        return response()->json([
            'tenants' => $tenants,
        ]);
    }

    /**
     * Select/switch to a tenant.
     * This generates a new token with tenant_id in claims.
     */
    public function select(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('api')->user();
        $tenantId = $request->tenant_id;

        // Verify user has access to this tenant
        $tenant = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenant_user.is_active', true)
            ->where('tenants.status', 'active')
            ->first();

        if (!$tenant) {
            return response()->json([
                'error' => 'You do not have access to this tenant'
            ], 403);
        }

        // Generate new token with tenant_id in custom claims
        $customClaims = [
            'email' => $user->email,
            'name' => $user->name,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'role' => $tenant->pivot->role,
        ];

        $token = auth('api')->claims($customClaims)->login($user);

        return response()->json([
            'message' => 'Tenant selected successfully',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'tenant' => [
                'id' => $tenant->id,
                'business_name' => $tenant->business_name,
                'slug' => $tenant->slug,
                'business_type' => $tenant->business_type,
                'role' => $tenant->pivot->role,
            ],
        ]);
    }

    /**
     * Get current tenant information.
     */
    public function current(): JsonResponse
    {
        $payload = auth('api')->payload();
        $tenantId = $payload->get('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'error' => 'No tenant selected. Please select a tenant first.'
            ], 400);
        }

        $user = auth('api')->user();
        $tenant = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->first(['tenants.*', 'tenant_user.role']);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found or access denied'
            ], 404);
        }

        return response()->json([
            'tenant' => $tenant,
            'role' => $tenant->pivot->role,
        ]);
    }
}
