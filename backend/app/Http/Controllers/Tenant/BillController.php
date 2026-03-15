<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Bill;
use App\Models\Tenant\LoyaltyLedger;
use App\Models\Tenant\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bill::with(['order', 'customer']);

        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        $bills = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json($bills);
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:tenant.orders,id',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string',
            'service_charge' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::findOrFail($request->order_id);

        // Check if bill already exists
        if ($order->bill) {
            return response()->json(['error' => 'Bill already exists for this order', 'bill' => $order->bill], 409);
        }

        $subtotal = (float) $order->subtotal;
        $taxAmount = (float) $order->tax_amount;
        $serviceCharge = $request->service_charge ?? 0;
        $deliveryCharge = (float) $order->delivery_charge;
        $packagingCharge = (float) ($order->packaging_charge ?? 0);
        $roundOff = (float) ($order->round_off ?? 0);

        // Calculate discount
        $discountAmount = 0;
        if ($request->discount_value) {
            $discountAmount = $request->discount_type === 'percentage'
                ? $subtotal * $request->discount_value / 100
                : $request->discount_value;
        }

        $total = $subtotal + $taxAmount + $serviceCharge + $deliveryCharge
            + $packagingCharge + $roundOff - $discountAmount;

        $bill = Bill::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_breakdown' => $order->tax_breakdown,
            'discount_amount' => $discountAmount,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'discount_reason' => $request->discount_reason,
            'service_charge' => $serviceCharge,
            'delivery_charge' => $deliveryCharge,
            'packaging_charge' => $packagingCharge,
            'round_off' => $roundOff,
            'total' => $total,
            'paid_amount' => 0,
            'balance' => $total,
            'payment_status' => 'unpaid',
            'cashier_id' => auth('api')->id(),
        ]);

        return response()->json(['bill' => $bill->load('order')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $bill = Bill::with(['order.items', 'customer'])->findOrFail($id);

        return response()->json(['bill' => $bill]);
    }

    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $bill = Bill::findOrFail($id);

        if ($bill->isPaid()) {
            return response()->json(['error' => 'Bill is already fully paid'], 400);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,upi,wallet,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate wallet redemption doesn't exceed balance
        if ($request->method === 'wallet' && $bill->customer_id) {
            $walletBalance = LoyaltyLedger::getBalance($bill->customer_id);
            if ($request->amount > $walletBalance) {
                return response()->json([
                    'error' => "Insufficient wallet balance. Available: {$walletBalance}",
                ], 422);
            }
            // Record wallet debit in ledger
            LoyaltyLedger::create([
                'customer_id' => $bill->customer_id,
                'bill_id'     => $bill->id,
                'type'        => 'debit',
                'amount'      => $request->amount,
                'description' => "Redeemed on bill #{$bill->bill_number}",
                'expires_at'  => null,
            ]);
        }

        $bill->recordPayment($request->amount, $request->method);

        // If fully paid, complete the order, free the table, record visit and award cashback.
        if ($bill->fresh()->isPaid()) {
            // Complete order and free the table
            $paidOrder = $bill->order()->with(['table', 'items.product'])->first();
            if ($paidOrder && !$paidOrder->isCompleted()) {
                $paidOrder->complete();
                if ($paidOrder->table_id) {
                    $paidOrder->table?->markAsAvailable();
                }
            }

            if ($bill->customer) {
                $bill->customer->recordVisit($bill->total);
            }

            // Award cashback for items with cb_percent > 0
            if ($bill->customer_id) {
                $tenant = request()->attributes->get('tenant');
                $expiryDays = $tenant?->settings['loyalty_expiry_days'] ?? 365;

                $order = $paidOrder ?? $bill->order()->with('items.product')->first();
                if ($order) {
                    foreach ($order->items as $item) {
                        $cbPercent = $item->product?->cb_percent ?? 0;
                        if ($cbPercent > 0) {
                            $earned = round((float) $item->total * $cbPercent / 100, 2);
                            LoyaltyLedger::create([
                                'customer_id' => $bill->customer_id,
                                'bill_id'     => $bill->id,
                                'type'        => 'credit',
                                'amount'      => $earned,
                                'description' => "Cashback on {$item->product_name}",
                                'expires_at'  => now()->addDays((int) $expiryDays),
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['bill' => $bill->fresh()->load('order')]);
    }

    public function applyDiscount(Request $request, int $id): JsonResponse
    {
        $bill = Bill::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bill->applyDiscount($request->value, $request->type, $request->reason);

        return response()->json(['bill' => $bill->fresh()]);
    }

    public function markPrinted(int $id): JsonResponse
    {
        $bill = Bill::findOrFail($id);
        $bill->markAsPrinted();

        return response()->json(['bill' => $bill->fresh()]);
    }
}
