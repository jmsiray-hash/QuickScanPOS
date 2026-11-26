<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $request->merge([
            'from' => $request->query('from', today()->toDateString()),
            'to' => $request->query('to', today()->toDateString()),
        ]);
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $validated['from'];
        $to = $validated['to'];
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $sales = Sale::query()
            ->with(['user', 'customer', 'voidedBy'])
            ->withCount('items')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summary = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $unitsSold = SaleItem::query()
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('created_at', [$start, $end]))
            ->sum('quantity');

        $topItems = SaleItem::query()
            ->selectRaw('product_name, barcode, SUM(quantity) as quantity_sold, SUM(line_total) as revenue')
            ->whereHas('sale', fn ($query) => $query->where('status', 'completed')->whereBetween('created_at', [$start, $end]))
            ->groupBy('product_name', 'barcode')
            ->orderByDesc('quantity_sold')
            ->orderBy('product_name')
            ->limit(10)
            ->get();

        $activeShift = CashierShift::query()->where('user_id', $request->user()->id)->where('status', 'open')->first();

        return view('sales.index', [
            'sales' => $sales,
            'from' => $from,
            'to' => $to,
            'revenue' => (float) $summary->revenue,
            'transactionCount' => (int) $summary->transaction_count,
            'unitsSold' => (int) $unitsSold,
            'topItems' => $topItems,
            'activeShift' => $activeShift,
            'activeShiftTotals' => $activeShift ? $this->shiftTotals($activeShift) : null,
            'shifts' => CashierShift::query()->with(['user', 'closedBy'])->latest('opened_at')->limit(10)->get(),
        ]);
    }

    public function closeShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $shift = DB::transaction(function () use ($request, $validated): CashierShift {
            $shift = CashierShift::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            $totals = $this->shiftTotals($shift);
            $expected = round((float) $shift->opening_cash + $totals['cash_sales'] - $totals['cash_refunds'], 2);
            $shift->update($totals + [
                'expected_cash' => $expected,
                'actual_cash' => (float) $validated['actual_cash'],
                'cash_over_short' => round((float) $validated['actual_cash'] - $expected, 2),
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
            ]);

            return $shift;
        });

        return to_route('sales.index')->with('status', 'Shift closed. Expected cash: ₱'.number_format((float) $shift->expected_cash, 2).'; over/short: ₱'.number_format((float) $shift->cash_over_short, 2).'.');
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->canVoidSales(), 403, 'Only an Admin or Manager can void a sale.');
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $sale, $validated): void {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            abort_unless($lockedSale->status === 'completed', 409, 'This sale has already been voided.');

            $items = $lockedSale->items()->lockForUpdate()->get();
            foreach ($items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                Product::query()->whereKey($item->product_id)->lockForUpdate()->increment('stock_quantity', $item->quantity);
            }

            $lockedSale->update([
                'status' => 'voided',
                'voided_by' => $request->user()->id,
                'voided_at' => now(),
                'void_reason' => trim($validated['void_reason']),
            ]);

            $shift = $lockedSale->shift_id
                ? CashierShift::query()->lockForUpdate()->find($lockedSale->shift_id)
                : null;
            if ($shift !== null && $shift->status === 'closed') {
                $totals = $this->shiftTotals($shift);
                $expected = round((float) $shift->opening_cash + $totals['cash_sales'] - $totals['cash_refunds'], 2);
                $shift->update($totals + [
                    'expected_cash' => $expected,
                    'cash_over_short' => round((float) $shift->actual_cash - $expected, 2),
                ]);
            }
        });

        return back()->with('status', 'Sale #'.$sale->id.' was voided. Stock was restored and the reason was recorded.');
    }

    public function show(Sale $sale): View
    {
        $sale->load(['user', 'customer', 'items', 'voidedBy']);
        $storeSettings = StoreSetting::current();

        $receiptData = [
            'id' => $sale->id,
            'store_name' => $sale->store_name ?: $storeSettings->store_name,
            'store_address' => $sale->store_address ?: $storeSettings->store_address,
            'created_at' => $sale->created_at->format('M j, Y g:i A'),
            'cashier' => $sale->user?->name ?? 'Deleted user',
            'customer_name' => $sale->customer_name,
            'subtotal' => (float) $sale->subtotal,
            'tax' => (float) $sale->tax,
            'discount' => (float) $sale->discount,
            'total' => (float) $sale->total,
            'payment_method' => $sale->payment_method,
            'cash_received' => $sale->cash_received === null ? null : (float) $sale->cash_received,
            'change_due' => (float) $sale->change_due,
            'payment_reference' => $sale->payment_reference,
            'status' => $sale->status,
            'void_reason' => $sale->void_reason,
            'items' => $sale->items->map(static fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'promo_unit_price' => $item->promo_unit_price === null ? null : (float) $item->promo_unit_price,
                'is_promo' => $item->is_promo,
                'discount_type' => $item->discount_type,
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'line_total' => (float) $item->line_total,
            ])->values(),
        ];

        return view('sales.show', compact('sale', 'receiptData', 'storeSettings'));
    }

    private function shiftTotals(CashierShift $shift): array
    {
        $completed = Sale::query()->where('shift_id', $shift->id)->where('status', 'completed');
        $voided = Sale::query()->where('shift_id', $shift->id)->where('status', 'voided');

        return [
            'cash_sales' => (float) (clone $completed)->where('payment_method', 'cash')->sum('total'),
            'cash_refunds' => (float) (clone $voided)->where('payment_method', 'cash')->sum('total'),
            'gcash_sales' => (float) (clone $completed)->where('payment_method', 'gcash')->sum('total'),
            'maya_sales' => (float) (clone $completed)->where('payment_method', 'maya')->sum('total'),
            'card_sales' => (float) (clone $completed)->where('payment_method', 'card')->sum('total'),
        ];
    }
}
