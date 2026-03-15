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
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['owner', 'manager', 'cashier', 'cook', 'waiter'])->default('cashier');
            $table->json('permissions')->nullable(); // Custom permissions per user per tenant
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Composite unique key to prevent duplicate entries
            $table->unique(['tenant_id', 'user_id']);

            // Indexes for performance
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
