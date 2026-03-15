<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaxPreviewController extends Controller
{
    public function __construct(private TaxService $taxService) {}

    /**
     * Preview tax breakdown for cart items without creating an order.
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.addons' => 'nullable|array',
            'customer_id' => 'nullable|integer|exists:tenant.customers,id',
            'packaging_charge' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = $request->attributes->get('tenant');
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;
        $packagingCharge = (float) ($request->packaging_charge ?? 0);

        $itemResults = [];
        $allBreakdowns = [];
        $totalSubtotal = 0;
        $totalTax = 0;

        foreach ($request->items as $itemData) {
            $product = Product::find($itemData['product_id']);
            if (!$product) {
                continue;
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

            $itemResults[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $itemDiscount,
                'tax_amount' => $taxResult['tax_amount'],
                'tax_breakdown' => $taxResult['tax_breakdown'],
                'tax_type' => $taxResult['tax_type'],
                'total' => round($subtotal + $taxResult['tax_amount'], 2),
            ];

            $allBreakdowns[] = $taxResult['tax_breakdown'];
            $totalSubtotal += $subtotal;
            $totalTax += $taxResult['tax_amount'];
        }

        $aggregatedBreakdown = $this->taxService->aggregateTaxBreakdown($allBreakdowns);
        $preRoundTotal = $totalSubtotal + $totalTax + $packagingCharge;
        $roundOff = $this->taxService->calculateRoundOff($preRoundTotal);

        return response()->json([
            'items' => $itemResults,
            'summary' => [
                'subtotal' => round($totalSubtotal, 2),
                'tax_amount' => round($totalTax, 2),
                'tax_breakdown' => $aggregatedBreakdown,
                'packaging_charge' => $packagingCharge,
                'round_off' => $roundOff,
                'total' => round($preRoundTotal + $roundOff, 2),
            ],
        ]);
    }
}
