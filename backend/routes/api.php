<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TenantController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\AddonGroupController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\BillController;
use App\Http\Controllers\Tenant\TableController;
use App\Http\Controllers\Tenant\KitchenStationController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\TenantSettingsController;
use App\Http\Controllers\Tenant\TaxPreviewController;
use App\Http\Controllers\Tenant\OrderItemController;
use App\Http\Controllers\Mobile\MobilePairingController;
use App\Http\Controllers\Mobile\ReportsController;
use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Cloud\Http\Controllers\Admin\CrmController;
use App\Cloud\Http\Controllers\Tenant\CrmLookupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function () {
    try {
        DB::connection('pgsql')->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'failed: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'ok',
        'service' => 'Flo POS API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
        'database' => $dbStatus,
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Password reset routes
    Route::post('password/forgot', [PasswordResetController::class, 'sendResetOtp']);
    Route::post('password/reset', [PasswordResetController::class, 'resetPassword']);

    // Protected routes (JWT required)
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('password/change', [PasswordResetController::class, 'changePassword']);

        // Tenant management
        Route::get('tenants', [TenantController::class, 'index']);
        Route::post('tenants/select', [TenantController::class, 'select']);
        Route::get('tenants/current', [TenantController::class, 'current']);
    });
});

/*
|--------------------------------------------------------------------------
| Tenant-Scoped Routes (JWT + tenant_id required)
|--------------------------------------------------------------------------
| All routes below require:
| 1. Valid JWT token (auth:api)
| 2. tenant_id in JWT claims (tenant middleware switches DB)
*/
Route::middleware(['auth:api', 'tenant', 'subscription'])->group(function () {

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::post('products/{id}/stock', [ProductController::class, 'updateStock']);

    // Addon Groups & Addons
    Route::apiResource('addon-groups', AddonGroupController::class);
    Route::post('addon-groups/{groupId}/addons', [AddonGroupController::class, 'addAddon']);
    Route::put('addon-groups/{groupId}/addons/{addonId}', [AddonGroupController::class, 'updateAddon']);
    Route::delete('addon-groups/{groupId}/addons/{addonId}', [AddonGroupController::class, 'deleteAddon']);

    // Orders
    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
    Route::post('orders/{id}/items', [OrderController::class, 'addItems']);
    Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('kitchen/orders', [OrderController::class, 'kitchen']);
    Route::patch('order-items/{id}/status', [OrderItemController::class, 'updateStatus']);

    // Bills
    Route::get('bills', [BillController::class, 'index']);
    Route::post('bills/generate', [BillController::class, 'generate']);
    Route::get('bills/{id}', [BillController::class, 'show']);
    Route::post('bills/{id}/payment', [BillController::class, 'recordPayment']);
    Route::post('bills/{id}/discount', [BillController::class, 'applyDiscount']);
    Route::post('bills/{id}/print', [BillController::class, 'markPrinted']);

    // Tables
    Route::apiResource('tables', TableController::class);
    Route::patch('tables/{id}/status', [TableController::class, 'updateStatus']);

    // Kitchen Stations
    Route::apiResource('kitchen-stations', KitchenStationController::class);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers-search', [CustomerController::class, 'search']);
    Route::get('customers/{id}/wallet', [CustomerController::class, 'wallet']);

    // Staff
    Route::apiResource('staff', StaffController::class);
    Route::post('staff/{id}/deactivate', [StaffController::class, 'deactivate']);
    Route::post('staff/{id}/reactivate', [StaffController::class, 'reactivate']);

    // Tax Settings
    Route::get('settings/tax', [TenantSettingsController::class, 'showTax']);
    Route::put('settings/tax', [TenantSettingsController::class, 'updateTax']);
    Route::put('settings/loyalty', [TenantSettingsController::class, 'updateLoyalty']);
    Route::get('settings/business', [TenantSettingsController::class, 'showBusiness']);
    Route::put('settings/business', [TenantSettingsController::class, 'updateBusiness']);

    // Tax Preview
    Route::post('tax/preview', [TaxPreviewController::class, 'preview']);
});

/*
|--------------------------------------------------------------------------
| Mobile Pairing Routes
|--------------------------------------------------------------------------
*/
// Public: pair a mobile device with a 6-char code
Route::post('mobile/pair', [MobilePairingController::class, 'pair']);

// Protected: manage pairing code (requires regular JWT)
Route::middleware('auth:api')->prefix('mobile')->group(function () {
    Route::get('pairing-code', [MobilePairingController::class, 'show']);
    Route::post('rotate-code', [MobilePairingController::class, 'rotate']);
});

/*
|--------------------------------------------------------------------------
| Mobile Reports Routes (scope-gated: requires scope=reports JWT)
|--------------------------------------------------------------------------
*/
Route::middleware('mobile_reports')->prefix('reports')->group(function () {
    Route::get('summary', [ReportsController::class, 'summary']);
    Route::get('sales', [ReportsController::class, 'sales']);
    Route::get('top-products', [ReportsController::class, 'topProducts']);
    Route::get('recent-orders', [ReportsController::class, 'recentOrders']);
    Route::get('tables', [ReportsController::class, 'tables']);
});

/*
|--------------------------------------------------------------------------
| Admin Panel Routes (Flopos cloud only — never registered on self-hosted)
|--------------------------------------------------------------------------
| Admin users are stored in a separate `admin_users` table and authenticate
| via the `admin` JWT guard. There is zero overlap with POS users.
*/
if (config('app.edition') === 'cloud') {
    Route::prefix('admin')->group(function () {
        // Public: admin login
        Route::post('auth/login', [AdminAuthController::class, 'login']);

        // Protected: requires valid admin JWT
        Route::middleware(['auth:admin', 'flopos_admin'])->group(function () {
            Route::post('auth/logout', [AdminAuthController::class, 'logout']);
            Route::post('auth/refresh', [AdminAuthController::class, 'refresh']);
            Route::get('auth/me', [AdminAuthController::class, 'me']);
            Route::post('auth/password', [AdminAuthController::class, 'changePassword']);

            // Tenant management
            Route::get('tenants', [TenantAdminController::class, 'index']);
            Route::get('tenants/{id}', [TenantAdminController::class, 'show']);
            Route::post('tenants/{id}/suspend', [TenantAdminController::class, 'suspend']);
            Route::post('tenants/{id}/reactivate', [TenantAdminController::class, 'reactivate']);
            Route::put('tenants/{id}/plan', [TenantAdminController::class, 'updatePlan']);

            // POS user management (read-only — admins cannot escalate POS users)
            Route::get('users', [TenantAdminController::class, 'users']);

            // Admin user management
            Route::get('admins', [AdminUserController::class, 'index']);
            Route::post('admins', [AdminUserController::class, 'store']);
            Route::put('admins/{id}', [AdminUserController::class, 'update']);
            Route::delete('admins/{id}', [AdminUserController::class, 'destroy']);

            // Central CRM
            Route::prefix('crm')->group(function () {
                Route::get('customers', [CrmController::class, 'index']);
                Route::post('customers', [CrmController::class, 'store']);
                Route::get('customers/{id}', [CrmController::class, 'show']);
                Route::put('customers/{id}', [CrmController::class, 'update']);
                Route::delete('customers/{id}', [CrmController::class, 'destroy']);
            });
        });
    });

    // CRM lookup for POS — tenant-scoped, cloud-only
    Route::middleware(['auth:api', 'tenant', 'subscription'])
        ->get('crm/lookup', [CrmLookupController::class, 'lookup']);
}
