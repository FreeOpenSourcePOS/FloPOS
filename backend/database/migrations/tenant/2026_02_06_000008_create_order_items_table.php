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
        Schema::connection('tenant')->create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('product_name'); // Stored in case product is deleted
            $table->string('product_sku')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 10, 2); // unit_price * quantity
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2); // subtotal + tax_amount
            $table->json('variant_selection')->nullable(); // Selected variant: {"Size": "M", "Color": "Red"}
            $table->json('modifier_selection')->nullable(); // Selected modifiers
            $table->json('addons')->nullable(); // Selected addons with prices
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])->default('pending');
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('order_id');
            $table->index('product_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('order_items');
    }
};
