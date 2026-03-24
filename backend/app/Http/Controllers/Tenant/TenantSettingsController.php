<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class TenantSettingsController extends Controller
{
    public function showTax(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $tenant->settings ?? [];

        return response()->json([
            'tax_registered' => (bool) ($settings['tax_registered'] ?? false),
            'gstin' => $settings['gstin'] ?? null,
            'state_code' => $settings['state_code'] ?? null,
            'tax_scheme' => $settings['tax_scheme'] ?? 'regular',
            'country' => $tenant->country,
            'business_type' => $tenant->business_type,
            'loyalty_expiry_days' => (int) ($settings['loyalty_expiry_days'] ?? 365),
        ]);
    }

    public function updateLoyalty(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'loyalty_expiry_days' => 'required|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = $tenant->settings ?? [];
        $settings['loyalty_expiry_days'] = $request->loyalty_expiry_days;
        $tenant->update(['settings' => $settings]);

        Cache::forget("tenant:{$tenant->id}");

        return response()->json([
            'message' => 'Loyalty settings updated',
            'loyalty_expiry_days' => $settings['loyalty_expiry_days'],
        ]);
    }

    public function updateTax(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'tax_registered' => 'required|boolean',
            'gstin' => 'nullable|string|size:15',
            'state_code' => 'nullable|string|size:2',
            'tax_scheme' => 'nullable|in:regular,composition',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Merge into existing settings
        $settings = $tenant->settings ?? [];
        $settings['tax_registered'] = $request->boolean('tax_registered');
        $settings['gstin'] = $request->gstin;
        $settings['state_code'] = $request->state_code;
        $settings['tax_scheme'] = $request->tax_scheme ?? 'regular';

        // Auto-extract state code from GSTIN if not provided
        if (!empty($settings['gstin']) && empty($settings['state_code'])) {
            $settings['state_code'] = substr($settings['gstin'], 0, 2);
        }

        $tenant->update(['settings' => $settings]);

        // Clear tenant cache
        Cache::forget("tenant:{$tenant->id}");

        return response()->json([
            'message' => 'Tax settings updated',
            'tax_registered' => $settings['tax_registered'],
            'gstin' => $settings['gstin'],
            'state_code' => $settings['state_code'],
            'tax_scheme' => $settings['tax_scheme'],
        ]);
    }

    public function showBusiness(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $tenant->settings ?? [];

        return response()->json([
            'business_name' => $tenant->business_name ?? '',
            'timezone' => $tenant->timezone ?? '',
            'currency' => $tenant->currency ?? '',
            'business_address' => $settings['business_address'] ?? '',
            'business_phone' => $settings['business_phone'] ?? '',
            'gstin' => $settings['gstin'] ?? '',
            'state_code' => $settings['state_code'] ?? '',
            'bill_show_name' => (bool) ($settings['bill_show_name'] ?? true),
            'bill_show_address' => (bool) ($settings['bill_show_address'] ?? true),
            'bill_show_phone' => (bool) ($settings['bill_show_phone'] ?? true),
            'bill_show_gstn' => (bool) ($settings['bill_show_gstn'] ?? false),
        ]);
    }

    public function updateBusiness(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'business_name' => 'nullable|string|max:200',
            'timezone' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'business_address' => 'nullable|string|max:500',
            'business_phone' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:15',
            'state_code' => 'nullable|string|max:2',
            'bill_show_name' => 'nullable|boolean',
            'bill_show_address' => 'nullable|boolean',
            'bill_show_phone' => 'nullable|boolean',
            'bill_show_gstn' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update tenant columns
        $tenantUpdate = [];
        if ($request->has('business_name') && $request->business_name) {
            $tenantUpdate['business_name'] = $request->business_name;
        }
        if ($request->has('timezone') && $request->timezone) {
            $tenantUpdate['timezone'] = $request->timezone;
        }
        if ($request->has('currency') && $request->currency) {
            $tenantUpdate['currency'] = $request->currency;
        }
        if (!empty($tenantUpdate)) {
            $tenant->update($tenantUpdate);
        }

        // Update settings JSON
        $settings = $tenant->settings ?? [];
        $settings['business_address'] = $request->business_address;
        $settings['business_phone'] = $request->business_phone;
        $settings['gstin'] = $request->gstin;
        $settings['state_code'] = $request->state_code;
        if ($request->has('bill_show_name')) $settings['bill_show_name'] = $request->boolean('bill_show_name');
        if ($request->has('bill_show_address')) $settings['bill_show_address'] = $request->boolean('bill_show_address');
        if ($request->has('bill_show_phone')) $settings['bill_show_phone'] = $request->boolean('bill_show_phone');
        if ($request->has('bill_show_gstn')) $settings['bill_show_gstn'] = $request->boolean('bill_show_gstn');

        $tenant->update(['settings' => $settings]);
        Cache::forget("tenant:{$tenant->id}");

        return response()->json([
            'message' => 'Business info updated',
            'business_name' => $tenant->fresh()->business_name,
            'timezone' => $tenant->fresh()->timezone,
            'currency' => $tenant->fresh()->currency,
            'business_address' => $settings['business_address'],
            'business_phone' => $settings['business_phone'],
            'gstin' => $settings['gstin'],
            'state_code' => $settings['state_code'],
            'bill_show_name' => $settings['bill_show_name'] ?? true,
            'bill_show_address' => $settings['bill_show_address'] ?? true,
            'bill_show_phone' => $settings['bill_show_phone'] ?? true,
            'bill_show_gstn' => $settings['bill_show_gstn'] ?? false,
        ]);
    }
}
