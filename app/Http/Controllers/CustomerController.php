<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'discount_id_type' => ['nullable', Rule::in(['pwd', 'senior'])],
            'discount_id_number' => ['nullable', 'required_if:discount_id_type,pwd,discount_id_type,senior', 'string', 'max:100'],
            'monthly_discount_limit' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'store_discount_reminder' => ['nullable', 'boolean'],
        ]);
        $validated['monthly_discount_limit'] ??= 0;
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $validated['store_discount_reminder'] = $request->boolean('store_discount_reminder');

        Customer::create($validated);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer created successfully.');
    }

    public function index(): View
    {
        $customers = Customer::query()->get();
        $usedByCustomer = $this->monthlyStoreDiscountTotals($customers->pluck('id')->all());
        foreach ($customers as $customer) {
            $customer->setAttribute('monthly_discount_used', $usedByCustomer[$customer->id] ?? 0);
            $customer->setAttribute(
                'monthly_discount_remaining',
                max(0, (float) $customer->monthly_discount_limit - (float) ($usedByCustomer[$customer->id] ?? 0)),
            );
        }

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['sales' => fn ($query) => $query->with('items')->latest()]);
        $used = $this->monthlyStoreDiscountTotals([$customer->id])[$customer->id] ?? 0;
        $customer->setAttribute('monthly_discount_used', $used);
        $customer->setAttribute(
            'monthly_discount_remaining',
            max(0, (float) $customer->monthly_discount_limit - (float) $used),
        );

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'discount_id_type' => ['nullable', Rule::in(['pwd', 'senior'])],
            'discount_id_number' => ['nullable', 'required_if:discount_id_type,pwd,discount_id_type,senior', 'string', 'max:100'],
            'monthly_discount_limit' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'store_discount_reminder' => ['nullable', 'boolean'],
        ]);
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $validated['store_discount_reminder'] = $request->boolean('store_discount_reminder');

        $customer->update($validated);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer deleted successfully.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:100'],
        ]);
        $term = trim($validated['search']);
        $customers = Customer::query()
            ->where(function ($query) use ($term): void {
                $query->where('full_name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%');
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get();

        $discountTotals = $this->monthlyStoreDiscountTotals($customers->pluck('id')->all());

        return response()->json([
            'customers' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'discount_id_type' => $customer->discount_id_type,
                'discount_id_number' => $customer->discount_id_number,
                'monthly_discount_limit' => (float) $customer->monthly_discount_limit,
                'monthly_discount_used' => (float) ($discountTotals[$customer->id] ?? 0),
                'monthly_discount_remaining' => max(
                    0,
                    (float) $customer->monthly_discount_limit - (float) ($discountTotals[$customer->id] ?? 0),
                ),
                'store_discount_reminder' => (bool) $customer->store_discount_reminder,
            ])->values(),
        ]);
    }

    private function monthlyStoreDiscountTotals(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return \App\Models\SaleItem::query()
            ->selectRaw('sales.customer_id, SUM(sale_items.discount_amount) as used')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.customer_id', $customerIds)
            ->where('sale_items.discount_type', 'store')
            ->whereBetween('sales.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('sales.customer_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->customer_id => (float) $row->used])
            ->all();
    }
}
