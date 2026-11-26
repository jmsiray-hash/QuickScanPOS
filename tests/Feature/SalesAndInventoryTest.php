<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\CashierShift;
use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_sales_report_filters_transactions_and_sums_sold_items(): void
    {
        $todaySale = $this->sale('12.50', now());
        $yesterdaySale = $this->sale('8.00', now()->subDay());
        $this->saleItem($todaySale, 'Apples', 'APPLE-1', 2, '6.00');
        $this->saleItem($yesterdaySale, 'Bread', 'BREAD-1', 1, '8.00');

        $this->get(route('sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('₱12.50')
            ->assertSee('Apples')
            ->assertSee('2')
            ->assertDontSee('Bread')
            ->assertSee(route('sales.show', $todaySale), false);
    }

    public function test_sale_receipt_displays_sold_product_snapshot(): void
    {
        $sale = $this->sale('15.00', now());
        $this->saleItem($sale, 'Discontinued Cereal', 'CEREAL-7', 3, '15.00');

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Sale Receipt #'.$sale->id)
            ->assertSee('Discontinued Cereal')
            ->assertSee('CEREAL-7')
            ->assertSee('Print Receipt')
            ->assertSee('Connect Bluetooth Printer')
            ->assertSee('Print to Bluetooth')
            ->assertSee('52 mm')
            ->assertSee('80 mm')
            ->assertSee('₱15.00');
    }

    public function test_inventory_can_be_searched_and_filtered_by_stock_level(): void
    {
        $this->product('LOW-1', 'Low stock cereal', 3);
        $this->product('OUT-1', 'Out of stock milk', 0);
        $this->product('ENOUGH-1', 'Enough rice', 20);

        $this->get(route('products.index', ['stock' => 'low']))
            ->assertOk()
            ->assertSee('Low stock cereal')
            ->assertDontSee('Out of stock milk')
            ->assertDontSee('Enough rice');

        $this->get(route('products.index', ['search' => 'milk', 'stock' => 'out']))
            ->assertOk()
            ->assertSee('Out of stock milk')
            ->assertDontSee('Low stock cereal')
            ->assertSee('Units in stock');
    }

    public function test_pos_can_search_products_and_manually_add_the_selected_quantity_to_cart(): void
    {
        $product = $this->product('MANUAL-POS-1', 'Manually Added Rice', 8);
        $this->product('MANUAL-POS-EMPTY', 'Out of stock rice', 0);

        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Add Product Manually')
            ->assertSee('Product name or barcode')
            ->assertSee('Products added here use their catalog price');

        $this->getJson(route('products.search-for-pos', ['q' => 'Manual']))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.name', 'Manually Added Rice')
            ->assertJsonPath('products.0.price', 2)
            ->assertJsonPath('products.0.stock_quantity', 8);

        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk()
            ->assertJsonPath('cart.items.0.product_id', $product->id)
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.items.0.line_total', 6)
            ->assertJsonPath('cart.total', 6);

        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 9,
        ])->assertUnprocessable();
    }

    public function test_cashier_dashboard_browses_available_products_by_category_and_adds_to_checkout(): void
    {
        $fruit = Product::create([
        'barcode' => 'DASHBOARD-APPLE',
        'name' => 'Green Apple',
        'category' => 'Fruits',
        'cost_price' => 1,
        'selling_price' => 3.25,
        'stock_quantity' => 5,
        'is_active' => true,
        ]);
        Product::create([
        'barcode' => 'DASHBOARD-BREAD',
        'name' => 'Fresh Bread',
        'category' => 'Bakery',
        'cost_price' => 1,
        'selling_price' => 4,
        'stock_quantity' => 3,
        'is_active' => true,
        ]);
        Product::create([
        'barcode' => 'DASHBOARD-EMPTY',
        'name' => 'Out of Stock Fruit',
        'category' => 'Fruits',
        'cost_price' => 1,
        'selling_price' => 2,
        'stock_quantity' => 0,
        'is_active' => true,
        ]);

        $this->get(route('cashier-dashboard'))
        ->assertOk()
        ->assertSee('Product Catalog')
        ->assertSee('Categories')
        ->assertSee('Fruits')
        ->assertSee('Bakery')
        ->assertSee('Green Apple')
        ->assertSee('Fresh Bread')
        ->assertDontSee('Out of Stock Fruit')
        ->assertSee(route('pos.store-qr'), false);

        $this->get(route('cashier-dashboard', ['category' => 'Fruits']))
        ->assertOk()
        ->assertSee('Green Apple')
        ->assertDontSee('Fresh Bread');

        $this->withSession(['pos_cart' => [
        (string) $fruit->id => [
            'product_id' => $fruit->id,
            'quantity' => 1,
            'unit_price' => 3.25,
        ],
        ]])->get(route('cashier-dashboard'))
        ->assertOk()
        ->assertSee('const cartQuantities = {"'.$fruit->id.'":1}', false);
    }

    public function test_checkout_requires_an_open_shift(): void
    {
        $product = $this->product('SHIFT-REQ-1', 'Shift required item', 5);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();

        $this->postJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'cash_received' => 10,
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Open a shift and record the opening cash fund before completing a sale.');

        $this->post(route('pos.shift.open'), ['opening_cash' => 100])
            ->assertRedirect(route('pos.index'));
        $this->postJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'cash_received' => 10,
        ])->assertOk()
            ->assertJsonPath('receipt.change_due', 8);
    }

    public function test_wallet_checkout_requires_and_records_reference_number(): void
    {
        $cashier = auth()->user();
        CashierShift::create(['user_id' => $cashier->id, 'opening_cash' => 100, 'opened_at' => now()]);
        $product = $this->product('WALLET-REF-1', 'Wallet item', 5);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();

        $this->postJson(route('pos.checkout'), ['payment_method' => 'gcash'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_reference');

        $checkout = $this->postJson(route('pos.checkout'), [
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-12345',
        ])->assertOk();

        $this->assertDatabaseHas('sales', [
            'id' => $checkout->json('sale_id'),
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-12345',
            'cash_received' => null,
        ]);
    }

    public function test_admin_or_manager_void_restores_stock_and_cashier_is_denied(): void
    {
        $cashier = auth()->user();
        $shift = CashierShift::create(['user_id' => $cashier->id, 'opening_cash' => 50, 'opened_at' => now()]);
        $product = $this->product('VOID-RESTORE-1', 'Void restore item', 4);
        $sale = Sale::create([
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'subtotal' => 2,
            'tax' => 0,
            'discount' => 0,
            'total' => 2,
            'payment_method' => 'cash',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2,
            'line_total' => 2,
        ]);

        $this->post(route('sales.void', $sale), ['void_reason' => 'Cashier entered the wrong item'])
            ->assertForbidden();

        $cashier->update(['role' => 'manager']);
        $this->post(route('sales.void', $sale), ['void_reason' => 'Cashier entered the wrong item'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => 'voided',
            'voided_by' => $cashier->id,
            'void_reason' => 'Cashier entered the wrong item',
        ]);
    }

    public function test_shift_closing_saves_expected_actual_and_over_short_with_payment_breakdown(): void
    {
        $cashier = auth()->user();
        $shift = CashierShift::create(['user_id' => $cashier->id, 'opening_cash' => 100, 'opened_at' => now()]);
        foreach ([['cash', 25], ['gcash', 60]] as [$method, $total]) {
            Sale::create([
                'user_id' => $cashier->id,
                'shift_id' => $shift->id,
                'subtotal' => $total,
                'tax' => 0,
                'discount' => 0,
                'total' => $total,
                'payment_method' => $method,
            ]);
        }

        $this->post(route('sales.shift.close'), ['actual_cash' => 124])
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'cash_sales' => 25,
            'cash_refunds' => 0,
            'gcash_sales' => 60,
            'expected_cash' => 125,
            'actual_cash' => 124,
            'cash_over_short' => -1,
        ]);
    }

    public function test_checkout_records_editable_promo_price_on_receipt(): void
    {
        $cashier = auth()->user();
        CashierShift::create(['user_id' => $cashier->id, 'opening_cash' => 100, 'opened_at' => now()]);
        $product = $this->product('PROMO-PRICE-1', 'Promo cereal', 5);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();
        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'promo_price' => 1.5,
        ])->assertOk()
            ->assertJsonPath('cart.items.0.is_promo', true);

        $checkout = $this->postJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'cash_received' => 2,
        ])->assertOk()
            ->assertJsonPath('receipt.items.0.is_promo', true)
            ->assertJsonPath('receipt.items.0.promo_unit_price', 1.5);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $checkout->json('sale_id'),
            'is_promo' => true,
            'promo_unit_price' => 1.5,
            'discount_amount' => 0.5,
            'line_total' => 1.5,
        ]);
    }

    public function test_senior_discount_requires_id_and_exempts_eligible_items_from_vat(): void
    {
        config(['pos.vat_rate' => 12]);
        $cashier = auth()->user();
        CashierShift::create(['user_id' => $cashier->id, 'opening_cash' => 100, 'opened_at' => now()]);
        $product = $this->product('SENIOR-VAT-1', 'Eligible item', 5);
        $product->update(['selling_price' => 112, 'senior_pwd_eligible' => true]);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();

        $this->postJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'cash_received' => 80,
            'special_discount_type' => 'senior',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('special_discount_id_number');

        $checkout = $this->postJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'cash_received' => 80,
            'special_discount_type' => 'senior',
            'special_discount_id_number' => 'SC-123456',
        ])->assertOk()
            ->assertJsonPath('receipt.subtotal', 100)
            ->assertJsonPath('receipt.tax', 0)
            ->assertJsonPath('receipt.discount', 20)
            ->assertJsonPath('receipt.total', 80);

        $this->assertDatabaseHas('sales', [
            'id' => $checkout->json('sale_id'),
            'special_discount_type' => 'senior',
            'special_discount_id_number' => 'SC-123456',
        ]);
    }

    public function test_product_can_be_removed_without_losing_sale_snapshots_or_orphaning_carts(): void
    {
        Storage::fake('public');
        $imagePath = 'products/remove-test.jpg';
        Storage::disk('public')->put($imagePath, 'product image');
        $product = Product::create([
            'barcode' => 'REMOVE-ME-1',
            'name' => 'Removed product',
            'category' => 'Grocery',
            'image_path' => $imagePath,
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        $sale = $this->sale('2.00', now());
        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2,
            'line_total' => 2,
        ]);
        $cart = CustomerCart::create([
            'token' => 'CART-REMOVE-TEST1',
            'customer_name' => 'Remove Test',
            'status' => 'shopping',
            'expires_at' => now()->addHour(),
        ]);
        $cartItem = CustomerCartItem::create([
            'customer_cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 2,
        ]);

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status', 'Product removed from inventory. Completed sales retain their saved item details.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('customer_cart_items', ['id' => $cartItem->id]);
        $this->assertDatabaseHas('sale_items', [
            'id' => $saleItem->id,
            'product_id' => null,
            'barcode' => 'REMOVE-ME-1',
            'product_name' => 'Removed product',
        ]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    private function sale(string $total, \Illuminate\Support\Carbon $createdAt): Sale
    {
        $sale = Sale::create([
            'user_id' => auth()->id(),
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
        ]);
        $sale->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $sale;
    }

    private function saleItem(Sale $sale, string $name, string $barcode, int $quantity, string $total): SaleItem
    {
        return $sale->items()->create([
            'barcode' => $barcode,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => (float) $total / $quantity,
            'line_total' => $total,
        ]);
    }

    private function product(string $barcode, string $name, int $stock): Product
    {
        return Product::create([
            'barcode' => $barcode,
            'name' => $name,
            'category' => 'Grocery',
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }
}
