<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Sale extends Model {
 use HasFactory;
 protected $fillable=['user_id','customer_id','customer_name','shift_id','subtotal','tax','discount','total','payment_method','cash_received','change_due','payment_reference','special_discount_type','special_discount_id_number','status','voided_by','voided_at','void_reason'];
 protected function casts(): array{return ['subtotal'=>'decimal:2','tax'=>'decimal:2','discount'=>'decimal:2','total'=>'decimal:2','cash_received'=>'decimal:2','change_due'=>'decimal:2','voided_at'=>'datetime'];}
 public function items():HasMany{return $this->hasMany(SaleItem::class);}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function customer():BelongsTo{return $this->belongsTo(Customer::class);}
 public function shift():BelongsTo{return $this->belongsTo(CashierShift::class,'shift_id');}
 public function voidedBy():BelongsTo{return $this->belongsTo(User::class,'voided_by');}
}
