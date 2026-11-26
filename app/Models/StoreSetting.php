<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = ['store_name', 'store_address'];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['store_name' => config('app.name'), 'store_address' => ''],
        );
    }
}
