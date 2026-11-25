<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->with(['user', 'customer'])
            ->withCount('items')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summary = Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $unitsSold = SaleItem::query()
            ->whereHas('sale', fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->sum('quantity');

        $topItems = SaleItem::query()
            ->selectRaw('product_name, barcode, SUM(quantity) as quantity_sold, SUM(line_total) as revenue')
            ->whereHas('sale', fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->groupBy('product_name', 'barcode')
            ->orderByDesc('quantity_sold')
            ->orderBy('product_name')
            ->limit(10)
            ->get();

        return view('sales.index', [
            'sales' => $sales,
            'from' => $from,
            'to' => $to,
            'revenue' => (float) $summary->revenue,
            'transactionCount' => (int) $summary->transaction_count,
            'unitsSold' => (int) $unitsSold,
            'topItems' => $topItems,
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['user', 'customer', 'items']);

        $receiptData = [
            'id' => $sale->id,
            'created_at' => $sale->created_at->format('M j, Y g:i A'),
            'cashier' => $sale->user?->name ?? 'Deleted user',
            'customer_name' => $sale->customer_name,
            'subtotal' => (float) $sale->subtotal,
            'tax' => (float) $sale->tax,
            'discount' => (float) $sale->discount,
            'total' => (float) $sale->total,
            'items' => $sale->items->map(static fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_type' => $item->discount_type,
                'discount_percent' => (float) $item->discount_percent,
                'discount_amount' => (float) $item->discount_amount,
                'line_total' => (float) $item->line_total,
            ])->values(),
        ];

        return view('sales.show', compact('sale', 'receiptData'));
    }
}
