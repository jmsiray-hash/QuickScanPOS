<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('sales',function(Blueprint $t):void{$t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->decimal('subtotal',10,2);$t->decimal('tax',10,2)->default(0);$t->decimal('discount',10,2)->default(0);$t->decimal('total',10,2);$t->timestamps();});}
 public function down():void{Schema::dropIfExists('sales');}
};
