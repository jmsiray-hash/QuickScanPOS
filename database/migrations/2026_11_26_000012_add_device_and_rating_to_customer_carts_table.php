<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->string('device_token', 64)->nullable()->index()->after('customer_id');
            $table->unsignedTinyInteger('service_rating')->nullable()->after('expires_at');
            $table->text('service_feedback')->nullable()->after('service_rating');
            $table->timestamp('rated_at')->nullable()->after('service_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table): void {
            $table->dropColumn(['device_token', 'service_rating', 'service_feedback', 'rated_at']);
        });
    }
};
