<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_customer_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cart_token', 40);
            $table->string('customer_name');
            $table->unsignedInteger('item_count');
            $table->timestamp('scanned_at')->index();
            $table->timestamps();
            $table->index(['cashier_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_customer_scans');
    }
};
