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
            ->with('user')
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
        $sale->load(['user', 'items']);

        return view('sales.show', compact('sale'));
    }
}
