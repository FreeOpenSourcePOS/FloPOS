<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderItemController extends Controller
{
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $item = OrderItem::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:preparing,ready,served',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        match ($request->status) {
            'preparing' => $item->markAsPreparing(),
            'ready' => $item->markAsReady(),
            'served' => $item->markAsServed(),
        };

        // Auto-sync parent order status based on item statuses
        $order = $item->order;
        $order->load('items');
        $this->syncOrderStatus($order);

        return response()->json([
            'item' => $item->fresh(),
            'order' => $order->fresh()->load('items', 'table'),
        ]);
    }

    private function syncOrderStatus(Order $order): void
    {
        $statuses = $order->items->pluck('status');

        if ($statuses->every(fn($s) => $s === 'served')) {
            if ($order->status !== 'served') {
                $order->markAsServed();
            }
        } elseif ($statuses->every(fn($s) => in_array($s, ['ready', 'served']))) {
            if (!in_array($order->status, ['ready', 'served'])) {
                $order->markAsReady();
            }
        } elseif ($statuses->contains('preparing')) {
            if ($order->status === 'pending') {
                $order->markAsPreparing();
            }
        }
    }
}
