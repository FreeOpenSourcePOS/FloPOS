<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Main\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantAdminController extends Controller
{
    /**
     * List all tenants with basic stats.
     * GET /api/admin/tenants
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withTrashed()
            ->with(['owner:id,name,email', 'subscription'])
            ->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $tenants = $query->paginate(20);

        return response()->json([
            'tenants' => $tenants->items(),
            'meta' => [
                'total' => $tenants->total(),
                'per_page' => $tenants->perPage(),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single tenant's full details.
     * GET /api/admin/tenants/{id}
     */
    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::withTrashed()
            ->with(['owner:id,name,email,phone', 'subscriptions', 'users:id,name,email'])
            ->findOrFail($id);

        return response()->json(['tenant' => $tenant]);
    }

    /**
     * Suspend a tenant.
     * POST /api/admin/tenants/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->suspend($request->input('reason'));

        return response()->json([
            'message' => 'Tenant suspended.',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Reactivate a suspended tenant.
     * POST /api/admin/tenants/{id}/reactivate
     */
    public function reactivate(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->reactivate();

        return response()->json([
            'message' => 'Tenant reactivated.',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Update a tenant's plan.
     * PUT /api/admin/tenants/{id}/plan
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:trial,basic,premium,enterprise',
            'trial_ends_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->update($validator->validated());

        return response()->json([
            'message' => 'Plan updated.',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * List all users.
     * GET /api/admin/users
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::withTrashed()
            ->with('tenants:id,business_name,slug')
            ->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return response()->json([
            'users' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Promote/demote a user to/from Flopos admin.
     * PUT /api/admin/users/{id}/admin
     */
    public function setAdmin(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_flopos_admin' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $user->update(['is_flopos_admin' => $request->boolean('is_flopos_admin')]);

        return response()->json([
            'message' => $user->is_flopos_admin ? 'User promoted to Flopos admin.' : 'Admin access revoked.',
            'user' => $user->only(['id', 'name', 'email', 'is_flopos_admin']),
        ]);
    }
}
