<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCartItem extends Model
{
    protected $fillable = [
        'customer_cart_id',
        'product_id',
        'qty',
        'checked_qty',
        'is_checked',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'checked_qty' => 'integer',
            'is_checked' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function customerCart(): BelongsTo
    {
        return $this->belongsTo(CustomerCart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
