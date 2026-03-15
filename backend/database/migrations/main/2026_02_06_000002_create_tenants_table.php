<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('restrict');
            $table->string('business_name');
            $table->string('slug')->unique(); // Used in database name: tenant_{slug}
            $table->string('database_name')->unique(); // tenant_restaurant_abc
            $table->enum('business_type', ['restaurant', 'salon', 'retail'])->default('restaurant');
            $table->string('country', 2)->default('IN'); // IN, TH
            $table->string('currency', 3)->default('INR'); // INR, THB
            $table->string('timezone')->default('Asia/Kolkata'); // Asia/Kolkata, Asia/Bangkok
            $table->enum('plan', ['trial', 'basic', 'premium', 'enterprise'])->default('trial');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->json('settings')->nullable(); // Business-specific settings
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('slug');
            $table->index('database_name');
            $table->index('owner_id');
            $table->index('status');
            $table->index('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
