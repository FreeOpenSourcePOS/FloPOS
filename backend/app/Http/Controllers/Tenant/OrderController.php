<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Table;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct(private TaxService $taxService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'table', 'customer']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'nullable|integer|exists:tenant.tables,id',
            'customer_id' => 'nullable|integer|exists:tenant.customers,id',
            'type' => 'required|in:dine_in,takeaway,delivery,online',
            'guest_count' => 'nullable|integer|min:1',
            'special_instructions' => 'nullable|string',
            'packaging_charge' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.variant_selection' => 'nullable|array',
            'items.*.modifier_selection' => 'nullable|array',
            'items.*.addons' => 'nullable|array',
            'items.*.special_instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::connection('tenant')->transaction(function () use ($request) {
            $tenant = $request->attributes->get('tenant');
            $customer = $request->customer_id ? Customer::find($request->customer_id) : null;

            $order = Order::create([
                'table_id' => $request->table_id,
                'customer_id' => $request->customer_id,
                'type' => $request->type,
                'guest_count' => $request->guest_count,
                'special_instructions' => $request->special_instructions,
                'packaging_charge' => $request->packaging_charge ?? 0,
                'status' => 'pending',
                'created_by' => auth('api')->id(),
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                // Decrease stock if tracking
                if ($product->track_inventory && !$product->decreaseStock($itemData['quantity'])) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                $unitPrice = (float) $product->price;
                $quantity = $itemData['quantity'];
                $itemDiscount = (float) ($itemData['discount_amount'] ?? 0);

                // Calculate subtotal (price * qty + addons - discount)
                $subtotal = $unitPrice * $quantity;
                if (!empty($itemData['addons'])) {
                    foreach ($itemData['addons'] as $addon) {
                        $subtotal += ($addon['price'] ?? 0) * $quantity;
                    }
                }
                $subtotal = max(0, $subtotal - $itemDiscount);

                // Calculate tax via TaxService
                $taxResult = $this->taxService->calculateItemTax($tenant, $product, $subtotal, $customer);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxResult['tax_amount'],
                    'tax_breakdown' => $taxResult['tax_breakdown'],
                    'tax_type' => $taxResult['tax_type'],
                    'discount_amount' => $itemDiscount,
                    'total' => $subtotal + $taxResult['tax_amount'],
                    'variant_selection' => $itemData['variant_selection'] ?? null,
                    'modifier_selection' => $itemData['modifier_selection'] ?? null,
                    'addons' => $itemData['addons'] ?? null,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                    'status' => 'pending',
                ]);
            }

            // Recalculate order totals with aggregated tax breakdown and round-off
            $order->recalculateWithTaxBreakdown($this->taxService);

            // Mark table as occupied for dine-in
            if ($request->table_id && $request->type === 'dine_in') {
                Table::find($request->table_id)?->markAsOccupied();
            }

            return response()->json([
                'order' => $order->load('items', 'table', 'customer'),
            ], 201);
        });
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'table', 'customer', 'bill'])->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    public function addItems(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json(['error' => 'Cannot add items to a completed or cancelled order'], 400);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.variant_selection' => 'nullable|array',
            'items.*.modifier_selection' => 'nullable|array',
            'items.*.addons' => 'nullable|array',
            'items.*.special_instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::connection('tenant')->transaction(function () use ($request, $order) {
            $tenant = $request->attributes->get('tenant');
            $customer = $order->customer_id ? Customer::find($order->customer_id) : null;

            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                if ($product->track_inventory && !$product->decreaseStock($itemData['quantity'])) {
                    throw new \Exception("Insufficient stock for {$product->name}");
                }

                $unitPrice = (float) $product->price;
                $quantity = $itemData['quantity'];
                $itemDiscount = (float) ($itemData['discount_amount'] ?? 0);

                $subtotal = $unitPrice * $quantity;
                if (!empty($itemData['addons'])) {
                    foreach ($itemData['addons'] as $addon) {
                        $subtotal += ($addon['price'] ?? 0) * $quantity;
                    }
                }
                $subtotal = max(0, $subtotal - $itemDiscount);

                $taxResult = $this->taxService->calculateItemTax($tenant, $product, $subtotal, $customer);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxResult['tax_amount'],
                    'tax_breakdown' => $taxResult['tax_breakdown'],
                    'tax_type' => $taxResult['tax_type'],
                    'discount_amount' => $itemDiscount,
                    'total' => $subtotal + $taxResult['tax_amount'],
                    'variant_selection' => $itemData['variant_selection'] ?? null,
                    'modifier_selection' => $itemData['modifier_selection'] ?? null,
                    'addons' => $itemData['addons'] ?? null,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                    'status' => 'pending',
                ]);
            }

            $order->recalculateWithTaxBreakdown($this->taxService);

            return response()->json(['order' => $order->fresh()->load('items', 'table', 'customer')]);
        });
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:preparing,ready,served,completed,cancelled',
            'reason' => 'required_if:status,cancelled|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        match ($request->status) {
            'preparing' => $order->markAsPreparing(),
            'ready' => $order->markAsReady(),
            'served' => $order->markAsServed(),
            'completed' => $this->completeOrder($order),
            'cancelled' => $this->cancelOrder($order, $request->reason),
        };

        return response()->json(['order' => $order->fresh()->load('items', 'table')]);
    }

    protected function completeOrder(Order $order): void
    {
        $order->complete();

        // Free up the table
        if ($order->table_id) {
            $order->table?->markAsAvailable();
        }
    }

    protected function cancelOrder(Order $order, ?string $reason): void
    {
        // Restore stock for cancelled items
        foreach ($order->items as $item) {
            if ($item->product && $item->product->track_inventory) {
                $item->product->increaseStock($item->quantity);
            }
        }

        $order->cancel($reason);

        if ($order->table_id) {
            $order->table?->markAsAvailable();
        }
    }

    // Kitchen display endpoints
    public function kitchen(Request $request): JsonResponse
    {
        // Fetch all active orders (those with at least one non-completed item)
        $orders = Order::with(['items', 'table'])
            ->whereHas('items', function ($q) {
                $q->whereIn('status', ['pending', 'preparing', 'ready', 'served']);
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest()
            ->get();

        // Counts based on item statuses, only from active orders
        $counts = \App\Models\Tenant\OrderItem::selectRaw("order_items.status, count(*) as count")
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['completed', 'cancelled'])
            ->whereIn('order_items.status', ['pending', 'preparing', 'ready', 'served'])
            ->groupBy('order_items.status')
            ->pluck('count', 'order_items.status');

        return response()->json([
            'orders' => $orders,
            'counts' => $counts,
        ]);
    }
}
