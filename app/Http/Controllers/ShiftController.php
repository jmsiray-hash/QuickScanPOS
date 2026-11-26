<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $opened = DB::transaction(function () use ($request, $validated): bool {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (CashierShift::query()->where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
                return false;
            }

            CashierShift::create([
                'user_id' => $request->user()->id,
                'opening_cash' => $validated['opening_cash'],
                'opened_at' => now(),
                'status' => 'open',
            ]);

            return true;
        });

        if (! $opened) {
            return back()->withErrors(['opening_cash' => 'You already have an open shift.']);
        }

        return $request->boolean('cashier_dashboard')
            ? to_route('cashier-dashboard')->with('status', 'Shift opened. Opening cash fund recorded.')
            : to_route('pos.index')->with('status', 'Shift opened. Opening cash fund recorded.');
    }
}
