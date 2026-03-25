<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('global_customer_id');
            $table->unsignedBigInteger('tenant_id');
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_redeemed', 12, 2)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->unique(['global_customer_id', 'tenant_id']);
            $table->index('global_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_wallet_balances');
    }
};
