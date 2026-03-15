<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'addonGroups.addons']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('active')) {
            $query->active();
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $products = $query->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:tenant.categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'cb_percent' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:none,inclusive,exclusive',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_code' => 'nullable|string|max:10',
            'track_inventory' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'available_online' => 'nullable|boolean',
            'image_url' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'variants' => 'nullable|array',
            'modifiers' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'addon_group_ids' => 'nullable|array',
            'addon_group_ids.*' => 'integer|exists:tenant.addon_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $addonGroupIds = $data['addon_group_ids'] ?? [];
        unset($data['addon_group_ids']);

        $product = Product::create($data);

        if (!empty($addonGroupIds)) {
            $product->addonGroups()->attach($addonGroupIds);
        }

        return response()->json(['product' => $product->load('category', 'addonGroups')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'addonGroups.addons'])->findOrFail($id);

        return response()->json(['product' => $product]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|integer|exists:tenant.categories,id',
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'cb_percent' => 'nullable|numeric|min:0|max:100',
            'tax_type' => 'nullable|in:none,inclusive,exclusive',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'hsn_code' => 'nullable|string|max:10',
            'track_inventory' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'available_online' => 'nullable|boolean',
            'image_url' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'variants' => 'nullable|array',
            'modifiers' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'addon_group_ids' => 'nullable|array',
            'addon_group_ids.*' => 'integer|exists:tenant.addon_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['addon_group_ids'])) {
            $product->addonGroups()->sync($data['addon_group_ids']);
            unset($data['addon_group_ids']);
        }

        $product->update($data);

        $result = $product->fresh()->load('category', 'addonGroups');

        return response()->json(['product' => $result]);
    }

    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    public function updateStock(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:set,increase,decrease',
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        match ($request->action) {
            'set' => $product->update(['stock_quantity' => $request->quantity]),
            'increase' => $product->increaseStock($request->quantity),
            'decrease' => $product->decreaseStock($request->quantity) ?: abort(400, 'Insufficient stock'),
        };

        return response()->json(['product' => $product->fresh()]);
    }
}
