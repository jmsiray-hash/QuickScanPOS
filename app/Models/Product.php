<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Product extends Model {
 use HasFactory;
 protected $fillable=['barcode','name','category','cost_price','selling_price','stock_quantity','is_active'];
 protected function casts(): array{return ['cost_price'=>'decimal:2','selling_price'=>'decimal:2','stock_quantity'=>'integer','is_active'=>'boolean'];}
 public function customerCartItems():HasMany{return $this->hasMany(CustomerCartItem::class);}
}
