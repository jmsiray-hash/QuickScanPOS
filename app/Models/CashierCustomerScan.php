<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierCustomerScan extends Model
{
    protected $fillable = [
        'cashier_id',
        'customer_cart_id',
        'cart_token',
        'customer_name',
        'item_count',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customerCart(): BelongsTo
    {
        return $this->belongsTo(CustomerCart::class);
    }
}
