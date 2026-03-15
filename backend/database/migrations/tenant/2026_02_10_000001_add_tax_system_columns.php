<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code', 10)->nullable()->after('tax_rate');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
            $table->string('tax_type', 10)->nullable()->after('tax_breakdown');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('tax_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
            $table->decimal('packaging_charge', 10, 2)->default(0)->after('delivery_charge');
            $table->decimal('round_off', 10, 2)->default(0)->after('packaging_charge');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
            $table->decimal('packaging_charge', 10, 2)->default(0)->after('delivery_charge');
            $table->decimal('round_off', 10, 2)->default(0)->after('packaging_charge');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('gstin', 15)->nullable()->after('notes');
            $table->string('customer_state_code', 2)->nullable()->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hsn_code');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['tax_breakdown', 'tax_type', 'discount_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_breakdown', 'packaging_charge', 'round_off']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['tax_breakdown', 'packaging_charge', 'round_off']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'customer_state_code']);
        });
    }
};
