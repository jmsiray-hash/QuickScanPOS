<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'opening_cash',
        'cash_sales',
        'cash_refunds',
        'gcash_sales',
        'maya_sales',
        'card_sales',
        'expected_cash',
        'actual_cash',
        'cash_over_short',
        'status',
        'opened_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'cash_refunds' => 'decimal:2',
            'gcash_sales' => 'decimal:2',
            'maya_sales' => 'decimal:2',
            'card_sales' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_over_short' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'shift_id');
    }
}
