<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Symfony\Component\HttpFoundation\Response;

class MobileReportsAuth
{
    public function __construct(protected TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = auth('api')->payload();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!$payload || $payload->get('scope') !== 'reports') {
            return response()->json([
                'error' => 'This token does not have reports access.',
            ], 403);
        }

        // Switch to the requested tenant DB
        $tenantId = $request->query('tenant_id') ?? $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'error' => 'tenant_id is required.',
            ], 400);
        }

        // Verify this user owns or has access to the requested tenant
        $user = auth('api')->user();
        $allowedTenantIds = $payload->get('tenant_ids') ?? [];

        if (!in_array((int) $tenantId, array_map('intval', $allowedTenantIds))) {
            return response()->json([
                'error' => 'Access to this tenant is not allowed.',
            ], 403);
        }

        $success = $this->tenantService->setTenant($tenantId);

        if (!$success) {
            return response()->json([
                'error' => 'Tenant not found or inactive.',
            ], 404);
        }

        $request->attributes->set('tenant', $this->tenantService->getTenant());

        $response = $next($request);

        $this->tenantService->clearTenant();

        return $response;
    }
}
