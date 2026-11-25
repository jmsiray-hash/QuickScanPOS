<?php

namespace Tests\Feature;

use App\Models\Product;
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
