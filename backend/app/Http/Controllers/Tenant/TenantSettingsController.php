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
            'business_address' => $settings['business_address'] ?? '',
            'business_phone' => $settings['business_phone'] ?? '',
            'gstin' => $settings['gstin'] ?? '',
            'state_code' => $settings['state_code'] ?? '',
        ]);
    }

    public function updateBusiness(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'business_address' => 'nullable|string|max:500',
            'business_phone' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|size:15',
            'state_code' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = $tenant->settings ?? [];
        $settings['business_address'] = $request->business_address;
        $settings['business_phone'] = $request->business_phone;
        $settings['gstin'] = $request->gstin;
        $settings['state_code'] = $request->state_code;

        $tenant->update(['settings' => $settings]);
        Cache::forget("tenant:{$tenant->id}");

        return response()->json([
            'message' => 'Business info updated',
            'business_address' => $settings['business_address'],
            'business_phone' => $settings['business_phone'],
            'gstin' => $settings['gstin'],
            'state_code' => $settings['state_code'],
        ]);
    }
}
