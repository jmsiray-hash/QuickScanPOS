<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('sale_items',function(Blueprint $t):void{$t->id();$t->foreignId('sale_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();$t->string('barcode');$t->string('product_name');$t->unsignedInteger('quantity');$t->decimal('unit_price',10,2);$t->decimal('line_total',10,2);$t->timestamps();});}
 public function down():void{Schema::dropIfExists('sale_items');}
};
