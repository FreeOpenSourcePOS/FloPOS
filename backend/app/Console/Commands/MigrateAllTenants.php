<?php

namespace App\Console\Commands;

use App\Models\Main\Tenant;
use App\Services\TenantService;
use Illuminate\Console\Command;

class MigrateAllTenants extends Command
{
    protected $signature = 'tenants:migrate {--force : Force running in production}';

    protected $description = 'Run pending migrations for all active tenant databases';

    public function handle(TenantService $tenantService): int
    {
        $tenants = Tenant::where('status', 'active')->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return 0;
        }

        $this->info("Migrating {$tenants->count()} tenant(s)...");

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                $tenantService->runMigrations($tenant);
                $this->line("  ✓ {$tenant->business_name} ({$tenant->database_name})");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ {$tenant->business_name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Done. ' . ($tenants->count() - $failed) . ' succeeded, ' . $failed . ' failed.');

        return $failed > 0 ? 1 : 0;
    }
}
