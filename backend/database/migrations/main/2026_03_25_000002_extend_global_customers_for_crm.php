<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_customers', function (Blueprint $table) {
            // Raw phone for admin search (the existing phone_hash is for open-source privacy)
            $table->string('phone', 20)->nullable()->after('phone_hash');

            // Extended address fields
            $table->text('address')->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('country', 5)->nullable()->default('IN')->after('postal_code');
            $table->date('date_of_birth')->nullable()->after('country');

            // CRM-specific profile fields
            $table->json('dietary_preferences')->nullable()->after('date_of_birth');
            $table->json('favourite_dishes')->nullable()->after('dietary_preferences');
            $table->text('notes')->nullable()->after('favourite_dishes');

            // Which tenant first added this customer
            $table->unsignedBigInteger('source_tenant_id')->nullable()->after('notes');

            $table->index('phone');
            $table->index('source_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('global_customers', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'address', 'city', 'state', 'postal_code', 'country',
                'date_of_birth', 'dietary_preferences', 'favourite_dishes', 'notes',
                'source_tenant_id',
            ]);
        });
    }
};
