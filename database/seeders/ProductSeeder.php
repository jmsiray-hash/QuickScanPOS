<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class ProductSeeder extends Seeder{public function run():void{$items=[['barcode'=>'4800012345678','name'=>'Premium Rice 5kg','category'=>'Grocery','cost_price'=>220,'selling_price'=>265,'stock_quantity'=>25],['barcode'=>'4801234567890','name'=>'Instant Coffee 20 Sachets','category'=>'Beverage','cost_price'=>95,'selling_price'=>125,'stock_quantity'=>40],['barcode'=>'012345678905','name'=>'Mineral Water 1L','category'=>'Beverage','cost_price'=>18,'selling_price'=>25,'stock_quantity'=>60]];foreach($items as $i)Product::updateOrCreate(['barcode'=>$i['barcode']],$i+['is_active'=>true]);}}
