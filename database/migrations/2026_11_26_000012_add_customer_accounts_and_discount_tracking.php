<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable();
            $table->string('discount_id_type', 20)->nullable();
            $table->string('discount_id_number', 100)->nullable();
            $table->decimal('monthly_discount_limit', 10, 2)->default(0);
            $table->boolean('store_discount_reminder')->default(false);
            $table->unique('email');
        });

        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable()->after('customer_id');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('discount_type', 20)->nullable()->after('unit_price');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_percent', 'discount_amount']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('customer_name');
        });

        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'password',
                'discount_id_type',
                'discount_id_number',
                'monthly_discount_limit',
                'store_discount_reminder',
            ]);
        });
    }
};
