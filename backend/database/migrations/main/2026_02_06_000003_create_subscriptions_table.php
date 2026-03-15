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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('razorpay_subscription_id')->nullable()->unique();
            $table->string('razorpay_customer_id')->nullable();
            $table->enum('plan', ['trial', 'basic', 'premium', 'enterprise'])->default('trial');
            $table->decimal('amount', 10, 2)->default(0); // Monthly amount
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->json('metadata')->nullable(); // Additional Razorpay data
            $table->timestamps();

            // Indexes for performance
            $table->index('tenant_id');
            $table->index('razorpay_subscription_id');
            $table->index('status');
            $table->index('current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
