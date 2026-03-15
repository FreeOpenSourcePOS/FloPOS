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
        Schema::connection('tenant')->create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // References users table in main database
            $table->string('employee_code')->unique()->nullable();
            $table->enum('role', ['manager', 'cashier', 'cook', 'waiter', 'delivery'])->default('cashier');
            $table->json('permissions')->nullable(); // Custom permissions
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('monthly_salary', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('employee_code');
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('staff');
    }
};
