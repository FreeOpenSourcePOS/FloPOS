<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->jsonb('tags')->nullable()->after('image_url');
        });

        // Migrate existing dietary_type values into the new tags array
        DB::connection('tenant')
            ->table('products')
            ->whereNotNull('dietary_type')
            ->get(['id', 'dietary_type'])
            ->each(function ($row) {
                DB::connection('tenant')->table('products')
                    ->where('id', $row->id)
                    ->update(['tags' => json_encode([$row->dietary_type])]);
            });

        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->dropColumn('dietary_type');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->string('dietary_type', 10)->nullable()->after('image_url');
        });

        Schema::connection('tenant')->table('products', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
