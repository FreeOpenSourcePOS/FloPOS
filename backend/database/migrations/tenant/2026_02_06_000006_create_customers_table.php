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
        Schema::connection('tenant')->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('country_code', 5)->default('+91');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('anniversary')->nullable();
            $table->integer('visits_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('average_bill', 10, 2)->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->json('preferences')->nullable(); // Favorite items, allergies, etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('phone');
            $table->index(['country_code', 'phone']);
            $table->index('email');
            $table->index('last_visit_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('customers');
    }
};
