<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Table::with('currentOrder');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('floor')) {
            $query->byFloor($request->floor);
        }

        if ($request->has('section')) {
            $query->bySection($request->section);
        }

        if ($request->has('active')) {
            $query->active();
        }

        $tables = $query->orderBy('floor')->orderBy('section')->orderBy('name')->get();

        return response()->json(['tables' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'kitchen_station_id' => 'nullable|integer|exists:tenant.kitchen_stations,id',
            'floor' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:50',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['status'] = 'available';

        $table = Table::create($data);

        return response()->json(['table' => $table], 201);
    }

    public function show(int $id): JsonResponse
    {
        $table = Table::with(['currentOrder.items', 'kitchenStation'])->findOrFail($id);

        return response()->json(['table' => $table]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'capacity' => 'sometimes|integer|min:1',
            'kitchen_station_id' => 'nullable|integer|exists:tenant.kitchen_stations,id',
            'floor' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:50',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $table->update($validator->validated());

        return response()->json(['table' => $table->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        if ($table->isOccupied()) {
            return response()->json(['error' => 'Cannot delete an occupied table'], 400);
        }

        $table->delete();

        return response()->json(['message' => 'Table deleted']);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,occupied,reserved,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $table->update(['status' => $request->status]);

        return response()->json(['table' => $table->fresh()]);
    }
}
