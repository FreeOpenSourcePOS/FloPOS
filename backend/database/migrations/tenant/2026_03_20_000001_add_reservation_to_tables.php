<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_customer_id')->nullable()->after('status');
            $table->string('reservation_customer_name')->nullable()->after('reservation_customer_id');
            $table->string('reservation_customer_phone')->nullable()->after('reservation_customer_name');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tables', function (Blueprint $table) {
            $table->dropColumn(['reservation_customer_id', 'reservation_customer_name', 'reservation_customer_phone']);
        });
    }
};
