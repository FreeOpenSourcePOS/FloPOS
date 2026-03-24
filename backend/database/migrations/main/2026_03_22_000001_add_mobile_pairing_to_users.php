<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_pairing_code', 6)->nullable()->unique()->after('is_active');
            $table->timestamp('mobile_pairing_code_rotated_at')->nullable()->after('mobile_pairing_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mobile_pairing_code', 'mobile_pairing_code_rotated_at']);
        });
    }
};
