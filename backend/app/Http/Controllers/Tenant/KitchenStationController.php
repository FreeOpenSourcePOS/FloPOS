<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\KitchenStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KitchenStationController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = KitchenStation::with('tables')->ordered()->get();

        return response()->json(['kitchen_stations' => $stations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:tenant.categories,id',
            'printer_ip' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $station = KitchenStation::create($validator->validated());

        return response()->json(['kitchen_station' => $station], 201);
    }

    public function show(int $id): JsonResponse
    {
        $station = KitchenStation::with('tables')->findOrFail($id);

        return response()->json(['kitchen_station' => $station]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $station = KitchenStation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:tenant.categories,id',
            'printer_ip' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $station->update($validator->validated());

        return response()->json(['kitchen_station' => $station->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $station = KitchenStation::findOrFail($id);
        $station->delete();

        return response()->json(['message' => 'Kitchen station deleted']);
    }
}
