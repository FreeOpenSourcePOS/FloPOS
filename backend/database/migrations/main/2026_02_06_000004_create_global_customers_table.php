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
        Schema::create('global_customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_hash')->unique(); // SHA256 hash of phone number for privacy
            $table->string('country_code', 5)->default('+91');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('consent_given')->default(false); // GDPR/Privacy compliance
            $table->timestamp('consent_given_at')->nullable();
            $table->integer('total_visits')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable(); // Additional customer data
            $table->timestamps();

            // Indexes for performance
            $table->index('phone_hash');
            $table->index('consent_given');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_customers');
    }
};
