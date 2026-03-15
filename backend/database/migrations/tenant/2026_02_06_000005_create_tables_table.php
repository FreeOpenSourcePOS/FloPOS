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
        Schema::connection('tenant')->create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Table 1", "A1"
            $table->integer('capacity')->default(4);
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
            $table->foreignId('kitchen_station_id')->nullable()->constrained('kitchen_stations')->onDelete('set null');
            $table->string('floor')->nullable(); // Floor number or section
            $table->string('section')->nullable(); // "Indoor", "Outdoor", "VIP"
            $table->integer('position_x')->nullable(); // For visual layout
            $table->integer('position_y')->nullable();
            $table->string('qr_code')->nullable(); // QR code for online ordering
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index('status');
            $table->index('is_active');
            $table->index('kitchen_station_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tables');
    }
};
