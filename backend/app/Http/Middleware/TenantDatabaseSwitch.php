<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Symfony\Component\HttpFoundation\Response;

class TenantDatabaseSwitch
{
    public function __construct(protected TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Get tenant_id from JWT payload
        $payload = auth('api')->payload();
        $tenantId = $payload->get('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'error' => 'No tenant selected. Please select a tenant first.',
            ], 400);
        }

        $success = $this->tenantService->setTenant($tenantId);

        if (!$success) {
            return response()->json([
                'error' => 'Unable to access tenant. Tenant may be inactive or not found.',
            ], 403);
        }

        // Attach tenant to request for controllers
        $request->attributes->set('tenant', $this->tenantService->getTenant());

        $response = $next($request);

        $this->tenantService->clearTenant();

        return $response;
    }
}
