<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AddonGroup;
use App\Models\Tenant\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddonGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = AddonGroup::with('addons')->ordered()->get();

        return response()->json(['addon_groups' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'nullable|boolean',
            'min_selection' => 'nullable|integer|min:0',
            'max_selection' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
            'addons' => 'nullable|array',
            'addons.*.name' => 'required_with:addons|string|max:255',
            'addons.*.price' => 'required_with:addons|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $addonsData = $data['addons'] ?? [];
        unset($data['addons']);

        $group = AddonGroup::create($data);

        foreach ($addonsData as $i => $addonData) {
            $group->addons()->create(array_merge($addonData, ['sort_order' => $i]));
        }

        return response()->json(['addon_group' => $group->load('addons')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $group = AddonGroup::with('addons')->findOrFail($id);

        return response()->json(['addon_group' => $group]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $group = AddonGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'nullable|boolean',
            'min_selection' => 'nullable|integer|min:0',
            'max_selection' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->update($validator->validated());

        return response()->json(['addon_group' => $group->fresh()->load('addons')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = AddonGroup::findOrFail($id);
        $group->addons()->delete();
        $group->delete();

        return response()->json(['message' => 'Addon group deleted']);
    }

    // Addon management within a group
    public function addAddon(Request $request, int $groupId): JsonResponse
    {
        $group = AddonGroup::findOrFail($groupId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $addon = $group->addons()->create($validator->validated());

        return response()->json(['addon' => $addon], 201);
    }

    public function updateAddon(Request $request, int $groupId, int $addonId): JsonResponse
    {
        $addon = Addon::where('addon_group_id', $groupId)->findOrFail($addonId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $addon->update($validator->validated());

        return response()->json(['addon' => $addon->fresh()]);
    }

    public function deleteAddon(int $groupId, int $addonId): JsonResponse
    {
        $addon = Addon::where('addon_group_id', $groupId)->findOrFail($addonId);
        $addon->delete();

        return response()->json(['message' => 'Addon deleted']);
    }
}
