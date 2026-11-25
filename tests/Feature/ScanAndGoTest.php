<?php

namespace Tests\Feature;

use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScanAndGoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_quickpos_home_and_dashboard_link_to_store_workflows(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('QuickScanPos')
            ->assertSee('Open POS Checkout')
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('products.create'), false)
            ->assertSee(route('shop.entry'), false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('POS Checkout')
            ->assertSee(route('products.create'), false)
            ->assertSee(route('products.index'), false)
            ->assertSee(route('pos.store-qr'), false)
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('users.index'), false);
    }

    public function test_customer_and_cashier_scanning_pages_render(): void
    {
        $this->get(route('shop.entry'))
            ->assertOk()
            ->assertSee('Start Shopping');
        $this->get(route('pos.store-qr'))
            ->assertOk()
            ->assertSee('Scan & Go Store Entry')
            ->assertSee('Phone-accessible store URL')
            ->assertSee('This address is only reachable on this computer');
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('autocomplete="off"', false)
            ->assertSee('Focus Scanner')
            ->assertSee('Scan Customer QR')
            ->assertSee('Scan Product Barcode')
            ->assertSee('Connect Cashier Phone')
            ->assertSee('no Bluetooth pairing')
            ->assertSee('Camera access requires HTTPS')
            ->assertSee('Camera permission was denied');
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Barcode / Printed Product Code')
            ->assertSee('autofocus', false)
            ->assertSee('Focus Scanner')
            ->assertSee('Connect Phone Online')
            ->assertDontSee('Bluetooth HID')
            ->assertSee('Camera permission was denied')
            ->assertSee('Connect Phone Online');
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Cashier Phone Item Check')
            ->assertDontSee('Bluetooth HID');
    }

    public function test_forwarded_https_host_is_used_for_urls_in_tunnel_requests(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'pos-example.devtunnels.ms',
                'X-Forwarded-Port' => '443',
            ])
            ->get('/')
            ->assertOk()
            ->assertSee('href="https://pos-example.devtunnels.ms"', false);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'pos-example.devtunnels.ms',
                'X-Forwarded-Port' => '443',
            ])
            ->get(route('pos.store-qr', absolute: false))
            ->assertOk()
            ->assertSee('value="https://pos-example.devtunnels.ms/shop"', false);
    }

    public function test_cashier_can_scan_a_product_code_with_a_phone_online(): void
    {
        $cashier = User::factory()->create();
        $this->actingAs($cashier);

        $session = $this->postJson(route('products.phone-scan.create'))
            ->assertOk()
            ->assertJsonStructure(['mobile_url', 'result_url', 'expires_in']);
        $token = basename(parse_url($session->json('mobile_url'), PHP_URL_PATH));

        $this->app['auth']->forgetGuards();
        $this->get(route('products.phone-scan.mobile', $token))
            ->assertOk()
            ->assertSee('Phone Product Scanner')
            ->assertSee('Read Printed Number / Can Code');

        $this->postJson(route('products.phone-scan.submit', $token), ['code' => ' phone-code-77 '])
            ->assertOk();

        $this->actingAs($cashier);
        $this->getJson(route('products.phone-scan.result', $token))
            ->assertOk()
            ->assertJsonPath('status', 'complete')
            ->assertJsonPath('code', 'PHONE-CODE-77');

        $this->getJson(route('products.phone-scan.result', $token))->assertGone();
    }

    public function test_registered_product_has_a_printable_qr_encoded_with_its_product_code(): void
    {
        $product = $this->product('QR-CODE-9988', 3, 10.00);

        $this->get(route('products.qr', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->barcode)
            ->assertSee('Print QR');

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee(route('products.qr', $product), false);
    }

    public function test_customer_can_scan_adjust_and_finalize_a_separate_cart(): void
    {
        $product = $this->product('CAN-PRINTED-77', 2, 12.50);

        $this->post(route('shop.start'), ['customer_name' => 'Taylor Shopper'])
            ->assertRedirect();

        $cart = CustomerCart::query()->where('customer_name', 'Taylor Shopper')->firstOrFail();
        $this->assertStringStartsWith('CART-', $cart->token);
        $this->assertSame('shopping', $cart->status);

        $this->get(route('shop.cart', $cart->token))
            ->assertOk()
            ->assertSee('Total Pieces')
            ->assertSee('Running Grand Total')
            ->assertSee('Remove item')
            ->assertSee('data-label="Quantity"', false)
            ->assertSee('grid-template-columns:repeat(2', false)
            ->assertSee('Read Printed Number / Can Code')
            ->assertSee('Camera access requires HTTPS')
            ->assertSee('X-CSRF-TOKEN');

        $this->postJson(route('shop.cart.scan', $cart->token), ['code' => ' can-printed-77 '])
            ->assertOk()
            ->assertJsonPath('cart.total_pieces', 1);
        $product->update(['selling_price' => 15.00]);
        $this->postJson(route('shop.cart.scan', $cart->token), ['code' => 'CAN-PRINTED-77'])
            ->assertOk()
            ->assertJsonPath('cart.total_pieces', 2)
            ->assertJsonPath('cart.grand_total', 25);
        $this->postJson(route('shop.cart.scan', $cart->token), ['code' => 'CAN-PRINTED-77'])
            ->assertUnprocessable();

        $item = $cart->items()->firstOrFail();
        $this->putJson(route('shop.cart.item.update', [$cart->token, $item->id]), ['qty' => 1])
            ->assertOk()
            ->assertJsonPath('cart.total_pieces', 1)
            ->assertJsonPath('cart.grand_total', 12.5);

        $this->putJson(route('shop.cart.item.update', [$cart->token, $item->id]), ['qty' => 0])
            ->assertOk()
            ->assertJsonPath('cart.total_pieces', 0);
        $this->assertDatabaseMissing('customer_cart_items', ['id' => $item->id]);
        $this->postJson(route('shop.cart.scan', $cart->token), ['code' => 'CAN-PRINTED-77'])
            ->assertOk()
            ->assertJsonPath('cart.total_pieces', 1);

        $this->postJson(route('shop.cart.finalize', $cart->token))
            ->assertOk()
            ->assertJsonPath('cart.status', 'ready_for_checkout')
            ->assertJsonPath('qr_value', $cart->token)
            ->assertJsonPath('qr_url', route('pos.verify', $cart->fresh()->verification_code))
            ->assertJsonPath('cart.verification_url', route('pos.verify', $cart->fresh()->verification_code));

        $this->assertSame('ready_for_checkout', $cart->fresh()->status);
        $this->assertNotNull($cart->fresh()->verification_code);

        $this->getJson(route('pos.import-cart', $cart->token))
            ->assertOk()
            ->assertJsonPath('verification_code', $cart->fresh()->verification_code)
            ->assertJsonPath('cart.is_verified', false);

        $this->get(route('pos.verify', $cart->fresh()->verification_code))
            ->assertOk()
            ->assertSee('Taylor Shopper');
    }

    public function test_cashier_can_verify_every_piece_and_checkout_the_imported_cart(): void
    {
        $product = $this->product('CAN-LABEL-005', 5, 9.00);
        $unexpectedProduct = $this->product('NOT-IN-CART', 5, 3.00);
        $cart = CustomerCart::create([
            'token' => 'CART-VERIFY-TEST1',
            'customer_name' => 'Morgan Shopper',
            'status' => 'ready_for_checkout',
            'expires_at' => now()->addHour(),
        ]);
        CustomerCartItem::create([
            'customer_cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 8.00,
        ]);

        $this->postJson(route('pos.scan'), ['code' => $cart->token])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 2);

        $this->postJson(route('pos.verification.create'))
            ->assertOk()
            ->assertJsonStructure(['verification_code', 'verification_url', 'status_url']);
        $verificationCode = $cart->fresh()->verification_code;
        $this->assertNotNull($verificationCode);

        $this->get(route('pos.verify', $verificationCode))
            ->assertOk()
            ->assertSee('Total Expected Pieces')
            ->assertSee('CASHIER PHONE')
            ->assertSee('statusTimer')
            ->assertSee('Morgan Shopper');

        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => 'NOT-IN-CART'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mismatch: this product is not in the expected cart.');
        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => 'UNKNOWN-CODE'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mismatch: no product matches that barcode or printed code.');

        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => 'CAN-LABEL-005'])
            ->assertOk()
            ->assertJsonPath('cart.total_checked_pieces', 1)
            ->assertJsonPath('cart.all_items_checked', false);

        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => 'CAN-LABEL-005'])
            ->assertOk()
            ->assertJsonPath('cart.total_checked_pieces', 2)
            ->assertJsonPath('cart.all_items_checked', true)
            ->assertJsonPath('label', 'SCANNED AND CHECKED ITEM - READY FOR PAYMENT');

        $this->getJson(route('pos.verify-status', $verificationCode))
            ->assertOk()
            ->assertJsonPath('is_verified', true)
            ->assertJsonPath('label', 'SCANNED AND CHECKED ITEM - READY FOR PAYMENT');
        $this->get(route('pos.verify', $verificationCode))
            ->assertOk()
            ->assertSee('SCANNED AND CHECKED ITEM - READY FOR PAYMENT');
        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => 'CAN-LABEL-005'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mismatch: the expected quantity for this item has already been checked.');

        $this->postJson(route('pos.checkout'))
            ->assertOk()
            ->assertJsonPath('message', 'Checkout completed.');

        $this->assertSame('paid', $cart->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 8,
            'line_total' => 16,
        ]);
        $this->getJson(route('pos.import-cart', $cart->token))->assertUnprocessable();
        $this->assertSame(5, $unexpectedProduct->fresh()->stock_quantity);
    }

    public function test_cashier_can_import_a_customer_cart_from_its_qr_url(): void
    {
        $product = $this->product('QR-CART-URL-1', 2, 11.00);
        $cart = CustomerCart::create([
            'token' => 'CART-URL-IMPORT1',
            'verification_code' => 'VERIFY-URL-IMPORT1',
            'customer_name' => 'URL Shopper',
            'status' => 'ready_for_checkout',
            'expires_at' => now()->addHour(),
        ]);
        CustomerCartItem::create([
            'customer_cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 11.00,
        ]);

        $this->postJson(route('pos.scan'), [
            'code' => route('pos.verify', $cart->fresh()->verification_code),
        ])
            ->assertOk()
            ->assertJsonPath('customer_cart_token', $cart->token)
            ->assertJsonPath('cart.items.0.barcode', $product->barcode);
    }

    public function test_customer_cart_checkout_requires_mobile_item_verification(): void
    {
        $product = $this->product('MANUAL-PAYMENT-1', 4, 6.00);
        $cart = CustomerCart::create([
            'token' => 'CART-MANUAL-PAY1',
            'customer_name' => 'Manual Override',
            'status' => 'ready_for_checkout',
            'expires_at' => now()->addHour(),
        ]);
        CustomerCartItem::create([
            'customer_cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 6.00,
        ]);

        $this->getJson(route('pos.import-cart', $cart->token))->assertOk();
        $this->postJson(route('pos.checkout'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Scan and verify every item with the cashier phone before checkout.');

        $this->assertSame('ready_for_checkout', $cart->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_cashier_cart_edits_reset_mobile_verification_before_payment(): void
    {
        $product = $this->product('CASHIER-EDIT-01', 5, 6.00);
        $addedProduct = $this->product('CASHIER-EDIT-02', 5, 4.00);
        $cart = CustomerCart::create([
            'token' => 'CART-CASHIER-EDIT1',
            'customer_name' => 'Edit Cart Shopper',
            'status' => 'ready_for_checkout',
            'expires_at' => now()->addHour(),
        ]);
        CustomerCartItem::create([
            'customer_cart_id' => $cart->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 6.00,
        ]);

        $this->postJson(route('pos.scan'), ['code' => $cart->token])->assertOk();
        $this->postJson(route('pos.verification.create'))->assertOk();
        $verificationCode = $cart->fresh()->verification_code;
        $this->postJson(route('pos.verify.check', $verificationCode), ['code' => $product->barcode])
            ->assertOk();

        $this->assertSame(1, $cart->fresh()->items()->firstOrFail()->checked_qty);
        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $cart->refresh();
        $item = $cart->items()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(3, $item->qty);
        $this->assertSame(0, $item->checked_qty);
        $this->assertFalse($item->is_checked);
        $this->assertFalse($cart->is_verified);

        $this->postJson(route('pos.scan'), ['code' => $addedProduct->barcode])->assertOk();
        $this->assertDatabaseHas('customer_cart_items', [
            'customer_cart_id' => $cart->id,
            'product_id' => $addedProduct->id,
            'qty' => 1,
        ]);
        $this->postJson(route('pos.cart.update'), [
            'product_id' => $addedProduct->id,
            'quantity' => 0,
        ])->assertOk();
        $this->assertDatabaseMissing('customer_cart_items', [
            'customer_cart_id' => $cart->id,
            'product_id' => $addedProduct->id,
        ]);
    }

    public function test_direct_pos_checkout_keeps_existing_live_price_and_stock_behavior(): void
    {
        $product = $this->product('DIRECT-POS-1', 3, 5.00);
        $this->postJson(route('pos.scan'), ['barcode' => $product->barcode])->assertOk();
        $product->update(['selling_price' => 7.00]);

        $this->postJson(route('pos.checkout'))
            ->assertOk()
            ->assertJsonPath('message', 'Checkout completed.');

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('sales', [
            'subtotal' => 7,
            'total' => 7,
        ]);
    }

    public function test_product_registration_and_pos_accept_a_printed_code_as_the_product_barcode(): void
    {
        $this->post(route('products.store'), [
            'barcode' => '  jar-code-19 ',
            'name' => 'Jar without barcode',
            'category' => 'Pantry',
            'cost_price' => 2,
            'selling_price' => 4,
            'stock_quantity' => 6,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('barcode', 'JAR-CODE-19')->firstOrFail();
        $this->postJson(route('pos.scan'), ['barcode' => ' jar-code-19 '])
            ->assertOk()
            ->assertJsonPath('cart.items.0.barcode', 'JAR-CODE-19');
    }

    private function product(string $code, int $stock, float $price): Product
    {
        return Product::create([
            'barcode' => $code,
            'name' => 'Test '.$code,
            'category' => 'Test',
            'cost_price' => 1,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }
}
