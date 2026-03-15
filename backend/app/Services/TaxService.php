<?php

namespace App\Services;

use App\Models\Main\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\Customer;

class TaxService
{
    /**
     * India GST rates by business type (as of Sept 2025).
     * Restaurants and salons: 5% (no ITC).
     * Retail: variable per product HSN code.
     */
    private const INDIA_FIXED_RATES = [
        'restaurant' => 5.0,
        'salon' => 5.0,
    ];

    private const THAILAND_VAT_RATE = 7.0;

    /**
     * Calculate tax breakdown for a single line item.
     */
    public function calculateItemTax(
        Tenant $tenant,
        Product $product,
        float $taxableAmount,
        ?Customer $customer = null
    ): array {
        if ($product->tax_type === 'none') {
            return [
                'tax_amount' => 0,
                'tax_breakdown' => [],
                'tax_type' => 'none',
            ];
        }

        if (!$this->isRegistered($tenant)) {
            return [
                'tax_amount' => 0,
                'tax_breakdown' => [],
                'tax_type' => $product->tax_type,
            ];
        }

        return match ($tenant->country) {
            'IN' => $this->calculateIndiaTax($tenant, $product, $taxableAmount, $customer),
            'TH' => $this->calculateThailandTax($product, $taxableAmount),
            default => $this->calculateDefaultTax($product, $taxableAmount),
        };
    }

    /**
     * Aggregate item-level tax breakdowns into an order-level breakdown.
     * Merges lines with same title+rate and sums amounts.
     */
    public function aggregateTaxBreakdown(array $itemBreakdowns): array
    {
        $merged = [];

        foreach ($itemBreakdowns as $breakdown) {
            if (!is_array($breakdown)) {
                continue;
            }
            foreach ($breakdown as $line) {
                $key = ($line['title'] ?? '') . '_' . ($line['rate'] ?? 0);
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'title' => $line['title'] ?? '',
                        'rate' => (float) ($line['rate'] ?? 0),
                        'amount' => 0,
                    ];
                }
                $merged[$key]['amount'] += (float) ($line['amount'] ?? 0);
            }
        }

        // Round amounts
        foreach ($merged as &$line) {
            $line['amount'] = round($line['amount'], 2);
        }

        return array_values($merged);
    }

    /**
     * Calculate round-off to nearest currency unit.
     */
    public function calculateRoundOff(float $total): float
    {
        $rounded = round($total);
        return round($rounded - $total, 2);
    }

    /**
     * Check if tenant is tax-registered.
     */
    private function isRegistered(Tenant $tenant): bool
    {
        $settings = $tenant->settings ?? [];
        return (bool) ($settings['tax_registered'] ?? false);
    }

    /**
     * India GST: CGST+SGST (intra-state) or IGST (inter-state).
     */
    private function calculateIndiaTax(
        Tenant $tenant,
        Product $product,
        float $taxableAmount,
        ?Customer $customer
    ): array {
        $rate = $this->determineIndiaTaxRate($tenant, $product);

        if ($rate <= 0) {
            return [
                'tax_amount' => 0,
                'tax_breakdown' => [],
                'tax_type' => $product->tax_type,
            ];
        }

        $taxAmount = $this->computeTaxAmount($product->tax_type, $taxableAmount, $rate);

        if ($this->shouldApplyIGST($tenant, $customer)) {
            return [
                'tax_amount' => round($taxAmount, 2),
                'tax_breakdown' => [
                    [
                        'title' => 'IGST',
                        'rate' => $rate,
                        'amount' => round($taxAmount, 2),
                    ],
                ],
                'tax_type' => $product->tax_type,
            ];
        }

        $halfRate = round($rate / 2, 2);
        $halfAmount = round($taxAmount / 2, 2);
        // Ensure the two halves sum to the total (handle rounding)
        $otherHalf = round($taxAmount - $halfAmount, 2);

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_breakdown' => [
                [
                    'title' => 'CGST',
                    'rate' => $halfRate,
                    'amount' => $halfAmount,
                ],
                [
                    'title' => 'SGST',
                    'rate' => $halfRate,
                    'amount' => $otherHalf,
                ],
            ],
            'tax_type' => $product->tax_type,
        ];
    }

    /**
     * Thailand: single VAT line at 7%.
     */
    private function calculateThailandTax(Product $product, float $taxableAmount): array
    {
        $rate = self::THAILAND_VAT_RATE;
        $taxAmount = $this->computeTaxAmount($product->tax_type, $taxableAmount, $rate);

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_breakdown' => [
                [
                    'title' => 'VAT',
                    'rate' => $rate,
                    'amount' => round($taxAmount, 2),
                ],
            ],
            'tax_type' => $product->tax_type,
        ];
    }

    /**
     * Default: single tax line using product's own tax_rate.
     */
    private function calculateDefaultTax(Product $product, float $taxableAmount): array
    {
        $rate = (float) $product->tax_rate;

        if ($rate <= 0) {
            return [
                'tax_amount' => 0,
                'tax_breakdown' => [],
                'tax_type' => $product->tax_type,
            ];
        }

        $taxAmount = $this->computeTaxAmount($product->tax_type, $taxableAmount, $rate);

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_breakdown' => [
                [
                    'title' => 'Tax',
                    'rate' => $rate,
                    'amount' => round($taxAmount, 2),
                ],
            ],
            'tax_type' => $product->tax_type,
        ];
    }

    /**
     * Determine the applicable tax rate for Indian businesses.
     * Restaurants/salons: fixed 5%. Retail: product-level rate.
     */
    private function determineIndiaTaxRate(Tenant $tenant, Product $product): float
    {
        if (isset(self::INDIA_FIXED_RATES[$tenant->business_type])) {
            return self::INDIA_FIXED_RATES[$tenant->business_type];
        }

        // Retail: use the product's own tax rate
        return (float) $product->tax_rate;
    }

    /**
     * Determine if IGST should apply instead of CGST+SGST.
     * IGST applies when customer has GSTIN from a different state.
     */
    private function shouldApplyIGST(Tenant $tenant, ?Customer $customer): bool
    {
        if (!$customer || !$customer->gstin || !$customer->customer_state_code) {
            return false;
        }

        $tenantState = ($tenant->settings ?? [])['state_code'] ?? null;
        if (!$tenantState) {
            return false;
        }

        return $customer->customer_state_code !== $tenantState;
    }

    /**
     * Compute tax amount based on inclusive/exclusive type.
     */
    private function computeTaxAmount(string $taxType, float $amount, float $rate): float
    {
        if ($taxType === 'inclusive') {
            // Tax is embedded in the amount: extract it
            return $amount - ($amount / (1 + $rate / 100));
        }

        // Exclusive: tax is added on top
        return $amount * $rate / 100;
    }
}
