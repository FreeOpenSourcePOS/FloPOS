<?php

namespace App\Services;

use App\Models\Main\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Exception;

class TenantService
{
    protected ?Tenant $currentTenant = null;

    /**
     * Set the current tenant and switch database connection.
     */
    public function setTenant(int $tenantId): bool
    {
        $tenant = Cache::remember("tenant:{$tenantId}", now()->addHours(24), function () use ($tenantId) {
            return Tenant::find($tenantId);
        });

        if (!$tenant || $tenant->status !== 'active') {
            return false;
        }

        $this->currentTenant = $tenant;
        $this->switchDatabase($tenant->database_name);

        return true;
    }

    /**
     * Switch the tenant database connection dynamically.
     */
    protected function switchDatabase(string $databaseName): void
    {
        DB::purge('tenant');
        Config::set('database.connections.tenant.database', $databaseName);
        DB::reconnect('tenant');
    }

    /**
     * Get current tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Create a new PostgreSQL database for a tenant and run migrations.
     */
    public function provisionDatabase(Tenant $tenant): void
    {
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '_', $tenant->database_name);

        if ($dbName !== $tenant->database_name) {
            $tenant->update(['database_name' => $dbName]);
        }

        $username = config('database.connections.pgsql.username');

        DB::connection('pgsql')->statement("CREATE DATABASE \"{$dbName}\"");
        DB::connection('pgsql')->statement("GRANT ALL PRIVILEGES ON DATABASE \"{$dbName}\" TO \"{$username}\"");

        $this->runMigrations($tenant);
    }

    /**
     * Run tenant migrations on a specific tenant database.
     */
    public function runMigrations(Tenant $tenant): void
    {
        $this->switchDatabase($tenant->database_name);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force' => true,
        ]);
    }

    /**
     * Check if a tenant database exists.
     */
    public function databaseExists(Tenant $tenant): bool
    {
        $result = DB::connection('pgsql')
            ->select("SELECT 1 FROM pg_database WHERE datname = ?", [$tenant->database_name]);

        return count($result) > 0;
    }

    /**
     * Get tenant by slug.
     */
    public function getTenantBySlug(string $slug): ?Tenant
    {
        return Cache::remember("tenant:slug:{$slug}", now()->addHours(24), function () use ($slug) {
            return Tenant::where('slug', $slug)->first();
        });
    }

    /**
     * Clear tenant from current context.
     */
    public function clearTenant(): void
    {
        $this->currentTenant = null;
        DB::purge('tenant');
    }
}
