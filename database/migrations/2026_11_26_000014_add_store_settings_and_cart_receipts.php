<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('store_name', 150);
            $table->text('store_address');
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('store_name', 150)->nullable();
            $table->text('store_address')->nullable();
        });

        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['store_name', 'store_address']);
        });

        Schema::dropIfExists('store_settings');
    }
};
