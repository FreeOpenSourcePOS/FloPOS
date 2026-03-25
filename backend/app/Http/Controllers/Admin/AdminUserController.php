<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    /**
     * List all admin users.
     * GET /api/admin/admins
     */
    public function index(): JsonResponse
    {
        $admins = AdminUser::latest()->get(['id', 'name', 'email', 'is_active', 'created_at']);
        return response()->json(['admins' => $admins]);
    }

    /**
     * Create a new admin user.
     * POST /api/admin/admins
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:admin_users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $admin = AdminUser::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => $request->password,
            'is_active' => true,
        ]);

        return response()->json(['admin' => $admin->only(['id', 'name', 'email', 'is_active', 'created_at'])], 201);
    }

    /**
     * Update an admin user.
     * PUT /api/admin/admins/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $admin = AdminUser::findOrFail($id);

        // Prevent an admin from deactivating their own account
        if ($admin->id === auth('admin')->id() && $request->has('is_active') && !$request->boolean('is_active')) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|string|max:100',
            'email'     => 'sometimes|email|unique:admin_users,email,' . $id,
            'is_active' => 'sometimes|boolean',
            'password'  => 'sometimes|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $admin->update($validator->validated());

        return response()->json(['admin' => $admin->fresh(['id', 'name', 'email', 'is_active', 'created_at'])]);
    }

    /**
     * Delete an admin user.
     * DELETE /api/admin/admins/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $admin = AdminUser::findOrFail($id);

        if ($admin->id === auth('admin')->id()) {
            return response()->json(['error' => 'You cannot delete your own account.'], 422);
        }

        $admin->delete();

        return response()->json(['message' => 'Admin user deleted.']);
    }
}
