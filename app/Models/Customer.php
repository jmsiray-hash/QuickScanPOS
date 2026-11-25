<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Customer extends Authenticatable
{
    protected $hidden = ['password'];

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'discount_id_type',
        'discount_id_number',
        'monthly_discount_limit',
        'store_discount_reminder',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'monthly_discount_limit' => 'decimal:2',
            'store_discount_reminder' => 'boolean',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => strtolower(trim($value)),
        );
    }

    public function carts(): HasMany
    {
        return $this->hasMany(CustomerCart::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
