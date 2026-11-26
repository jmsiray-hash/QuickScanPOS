<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('cashier');
        });

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['gsiray49@gmail.com'])
            ->update(['role' => 'admin']);

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('senior_pwd_eligible')->default(false);
        });

        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_cash', 10, 2);
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('cash_refunds', 10, 2)->default(0);
            $table->decimal('gcash_sales', 10, 2)->default(0);
            $table->decimal('maya_sales', 10, 2)->default(0);
            $table->decimal('card_sales', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('actual_cash', 10, 2)->nullable();
            $table->decimal('cash_over_short', 10, 2)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->string('payment_method', 20)->default('cash');
            $table->decimal('cash_received', 10, 2)->nullable();
            $table->decimal('change_due', 10, 2)->default(0);
            $table->string('payment_reference', 100)->nullable();
            $table->string('special_discount_type', 20)->nullable();
            $table->string('special_discount_id_number', 100)->nullable();
            $table->string('status', 20)->default('completed')->index();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('promo_unit_price', 10, 2)->nullable();
            $table->boolean('is_promo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['promo_unit_price', 'is_promo']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn([
                'shift_id',
                'payment_method',
                'cash_received',
                'change_due',
                'payment_reference',
                'special_discount_type',
                'special_discount_id_number',
                'status',
                'voided_by',
                'voided_at',
                'void_reason',
            ]);
        });

        Schema::dropIfExists('cashier_shifts');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('senior_pwd_eligible');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
