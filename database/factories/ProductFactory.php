<?php
namespace Database\Factories;
use Illuminate\Database\EloquentFactoriesFactory;
class ProductFactory extends Factory{public function definition():array{return['barcode'=>fake()->unique()->ean13(),'name'=>fake()->words(3,true),'category'=>fake()->randomElement(['Grocery','Beverage','Household']),'cost_price'=>fake()->randomFloat(2,1,100),'selling_price'=>fake()->randomFloat(2,2,150),'stock_quantity'=>fake()->numberBetween(1,100),'is_active'=>true];}}
