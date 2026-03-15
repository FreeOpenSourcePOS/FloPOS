<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Staff::query();

        if ($request->has('role')) {
            $query->byRole($request->role);
        }

        if ($request->has('active')) {
            $query->active();
        }

        $staff = $query->orderBy('role')->orderBy('employee_code')->get();

        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'employee_code' => 'required|string|max:50',
            'role' => 'required|in:manager,cashier,waiter,cook,bartender,host',
            'permissions' => 'nullable|array',
            'hourly_rate' => 'nullable|numeric|min:0',
            'monthly_salary' => 'nullable|numeric|min:0',
            'joined_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['is_active'] = true;

        $staff = Staff::create($data);

        return response()->json(['staff' => $staff], 201);
    }

    public function show(int $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);

        return response()->json(['staff' => $staff]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_code' => 'sometimes|string|max:50',
            'role' => 'sometimes|in:manager,cashier,waiter,cook,bartender,host',
            'permissions' => 'nullable|array',
            'hourly_rate' => 'nullable|numeric|min:0',
            'monthly_salary' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff->update($validator->validated());

        return response()->json(['staff' => $staff->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);
        $staff->delete();

        return response()->json(['message' => 'Staff member deleted']);
    }

    public function deactivate(int $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);
        $staff->deactivate();

        return response()->json(['staff' => $staff->fresh()]);
    }

    public function reactivate(int $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);
        $staff->reactivate();

        return response()->json(['staff' => $staff->fresh()]);
    }
}
