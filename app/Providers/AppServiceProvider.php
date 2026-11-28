<?php

namespace App\Providers;

use App\Models\CashierCustomerScan;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StoreSetting;
use App\Models\User;
use App\Observers\SharedDataObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        foreach ([
            Product::class,
            User::class,
            Customer::class,
            CustomerCart::class,
            CustomerCartItem::class,
            Sale::class,
            SaleItem::class,
            CashierShift::class,
            CashierCustomerScan::class,
            StoreSetting::class,
        ] as $model) {
            $model::observe(SharedDataObserver::class);
        }
    }
}
