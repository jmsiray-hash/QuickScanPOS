<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCart extends Model
{
    protected $fillable = [
        'token',
        'verification_code',
        'is_verified',
        'verified_at',
        'customer_name',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerCartItem::class);
    }
}
