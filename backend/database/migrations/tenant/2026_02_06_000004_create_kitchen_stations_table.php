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
        Schema::connection('tenant')->create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Grill Station", "Beverage Station"
            $table->text('description')->nullable();
            $table->json('category_ids')->nullable(); // Categories assigned to this station
            $table->boolean('is_active')->default(true);
            $table->string('printer_ip')->nullable(); // For KOT printing
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes for performance
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('kitchen_stations');
    }
};
