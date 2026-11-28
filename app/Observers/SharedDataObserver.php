<?php

namespace App\Observers;

use App\Events\SharedDataChanged;
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
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SharedDataObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Model $model): void
    {
        $this->broadcastChange($model);
    }

    public function updated(Model $model): void
    {
        $this->broadcastChange($model);
    }

    public function deleted(Model $model): void
    {
        $this->broadcastChange($model);
    }

    private function broadcastChange(Model $model): void
    {
        $resource = match (true) {
            $model instanceof Product => 'product',
            $model instanceof User => 'user',
            $model instanceof Customer => 'customer',
            $model instanceof CustomerCart, $model instanceof CustomerCartItem => 'cart',
            $model instanceof Sale, $model instanceof SaleItem => 'sale',
            $model instanceof CashierShift => 'shift',
            $model instanceof CashierCustomerScan => 'scan',
            $model instanceof StoreSetting => 'settings',
            default => null,
        };

        if ($resource === null) {
            return;
        }

        $cartToken = match (true) {
            $model instanceof CustomerCart => $model->token,
            $model instanceof CustomerCartItem => $model->customerCart?->token,
            default => null,
        };

        try {
            event(new SharedDataChanged($resource, $cartToken));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
