<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('customers', function (Blueprint $table) {
            // Link to central CRM (nullable — self-hosted tenants never set this)
            $table->unsignedBigInteger('global_customer_id')->nullable()->after('id');
            // Tenant-specific CRM overrides (e.g., this restaurant knows the customer's spice preference)
            $table->json('dietary_preferences')->nullable()->after('preferences');
            $table->json('favourite_dishes')->nullable()->after('dietary_preferences');

            $table->index('global_customer_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('customers', function (Blueprint $table) {
            $table->dropColumn(['global_customer_id', 'dietary_preferences', 'favourite_dishes']);
        });
    }
};
