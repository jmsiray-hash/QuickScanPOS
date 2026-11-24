<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('checked_qty')->default(0);
            $table->boolean('is_checked')->default(false);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['customer_cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cart_items');
    }
};
