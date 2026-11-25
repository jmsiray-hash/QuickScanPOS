<?php

namespace Tests\Feature;

use App\Models\CustomerCart;
use App\Models\CustomerCartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ScanAndGoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->signInShopper('Default Shopper');
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
            ->assertSee('Camera permission was denied')
            ->assertSee('fps: 15')
            ->assertSee('viewfinderWidth * 0.9');
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Barcode / Printed Product Code')
            ->assertSee('autofocus', false)
            ->assertSee('Focus Scanner')
            ->assertSee('Connect Phone Online')
            ->assertDontSee('Bluetooth HID')
            ->assertSee('Camera permission was denied')
            ->assertSee('Connect Phone Online')
            ->assertSee('fps: 15')
            ->assertSee('viewfinderWidth * 0.9');
        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Cashier Phone Item Check')
            ->assertSee('Customer &amp; Discounts', false)
            ->assertSee('Monthly store-discount quota')
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
            ->assertSee('Read Printed Number / Can Code')
            ->assertSee('Save Product to Inventory')
            ->assertSee('Take a photo of this product')
            ->assertSee('Save Product to Inventory')
            ->assertSee('Product name')
            ->assertSee('Selling price');

        $phonePhoto = UploadedFile::fake()->image('phone-photo.png', 10, 10);
        $photoData = 'data:image/png;base64,'.base64_encode(file_get_contents($phonePhoto->getRealPath()));
        $this->postJson(route('products.phone-scan.submit', $token), [
            'code' => ' phone-code-77 ',
            'photo' => $photoData,
        ])
            ->assertOk();

        $this->actingAs($cashier);
        $this->getJson(route('products.phone-scan.result', $token))
            ->assertOk()
            ->assertJsonPath('status', 'complete')
            ->assertJsonPath('code', 'PHONE-CODE-77')
            ->assertJsonPath('photo_data', $photoData);

        $this->getJson(route('products.phone-scan.result', $token))
            ->assertOk()
            ->assertJsonPath('status', 'waiting');
        $this->postJson(route('products.phone-scan.submit', $token), ['code' => 'PHONE-CODE-78'])
            ->assertOk();
        $this->getJson(route('products.phone-scan.result', $token))
            ->assertOk()
            ->assertJsonPath('code', 'PHONE-CODE-78');
    }

    public function test_phone_product_scanner_saves_photo_and_details_to_inventory(): void
    {
        Storage::fake('public');
        $session = $this->postJson(route('products.phone-scan.create'))->assertOk();
        $token = basename(parse_url($session->json('mobile_url'), PHP_URL_PATH));
        $photo = UploadedFile::fake()->image('mobile-product.png', 10, 10);
        $photoData = 'data:image/png;base64,'.base64_encode(file_get_contents($photo->getRealPath()));

        $this->postJson(route('products.phone-scan.save', $token), [
            'barcode' => ' phone-saved-55 ',
            'name' => 'Phone Saved Oats',
            'category' => 'Breakfast',
            'cost_price' => 3.25,
            'selling_price' => 5.75,
            'stock_quantity' => 18,
            'photo' => $photoData,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Product details and photo saved to inventory.')
            ->assertJsonPath('product.barcode', 'PHONE-SAVED-55')
            ->assertJsonPath('product.name', 'Phone Saved Oats')
            ->assertJsonPath('product.selling_price', 5.75)
            ->assertJsonPath('product.stock_quantity', 18);

        $product = Product::query()->where('barcode', 'PHONE-SAVED-55')->firstOrFail();
        $this->assertSame('Breakfast', $product->category);
        $this->assertSame('3.25', $product->cost_price);
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->get(route('products.image', $product))->assertOk();

        $this->postJson(route('products.phone-scan.save', $token), [
            'barcode' => 'PHONE-SAVED-55',
            'name' => 'Duplicate Oats',
            'cost_price' => 3,
            'selling_price' => 5,
            'stock_quantity' => 1,
            'photo' => $photoData,
        ])->assertUnprocessable();
    }

    public function test_product_registration_can_save_and_open_a_fresh_form_for_the_next_item(): void
    {
        $this->post(route('products.store'), [
            'barcode' => '  CONTINUOUS-ITEM-1 ',
            'name' => 'First continuous item',
            'category' => 'Grocery',
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => 10,
            'is_active' => 1,
            'continue' => 1,
        ])->assertRedirect(route('products.create'));

        $this->assertDatabaseHas('products', [
            'barcode' => 'CONTINUOUS-ITEM-1',
            'name' => 'First continuous item',
            'stock_quantity' => 10,
        ]);
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('Save &amp; Register Next', false)
            ->assertSee('Product saved. Scan or enter the next item.');
    }

    public function test_continuous_product_registration_saves_without_leaving_the_form(): void
    {
        $this->postJson(route('products.store'), [
            'barcode' => 'CONTINUOUS-ITEM-2',
            'name' => 'Second continuous item',
            'category' => 'Grocery',
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => 4,
            'is_active' => 1,
            'continue' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Product saved. Scan or enter the next item.')
            ->assertJsonPath('product.barcode', 'CONTINUOUS-ITEM-2');

        $this->assertDatabaseHas('products', [
            'barcode' => 'CONTINUOUS-ITEM-2',
            'name' => 'Second continuous item',
            'stock_quantity' => 4,
        ]);
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

    public function test_product_photo_is_saved_and_shown_in_the_customer_cart(): void
    {
        Storage::fake('public');

        $this->post(route('products.store'), [
            'barcode' => 'PHOTO-APPLE-1',
            'name' => 'Fresh Apples',
            'category' => 'Produce',
            'image' => UploadedFile::fake()->image('apples.jpg', 10, 10),
            'cost_price' => 1,
            'selling_price' => 2.5,
            'stock_quantity' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->where('barcode', 'PHOTO-APPLE-1')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->get(route('products.image', $product))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->signInShopper('Photo Shopper');
        $this->post(route('shop.start'))->assertRedirect();
        $cart = CustomerCart::query()->where('customer_name', 'Photo Shopper')->firstOrFail();
        $this->postJson(route('shop.cart.scan', $cart->token), ['code' => $product->barcode])
            ->assertOk()
            ->assertJsonPath('cart.items.0.image_url', route('products.image', $product));
        $this->get(route('shop.cart', $cart->token))
            ->assertOk()
            ->assertSee('shop-product-image');
    }

    public function test_customer_can_scan_adjust_and_finalize_a_separate_cart(): void
    {
        $product = $this->product('CAN-PRINTED-77', 2, 12.50);

        $this->signInShopper('Taylor Shopper');
        $this->post(route('shop.start'))
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

    public function test_cashier_phone_can_connect_before_items_are_added(): void
    {
        $product = $this->product('PHONE-FIRST-ITEM', 3, 4.50);

        $connection = $this->postJson(route('pos.verification.create'))
            ->assertOk()
            ->assertJsonPath('message', 'Cashier phone connected. Add items at the register; the phone will update automatically.')
            ->assertJsonStructure(['customer_cart_token', 'verification_code', 'verification_url', 'status_url']);

        $cart = CustomerCart::query()->where('token', $connection->json('customer_cart_token'))->firstOrFail();
        $this->assertSame(0, $cart->items()->count());
        $this->get(route('pos.verify', $connection->json('verification_code')))
            ->assertOk()
            ->assertSee('Phone connected. Waiting for the cashier to add items.')
            ->assertSee('Waiting for the cashier to add items to this checkout.');

        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();
        $this->getJson(route('pos.verify-status', $connection->json('verification_code')))
            ->assertOk()
            ->assertJsonPath('cart.total_expected_pieces', 1)
            ->assertJsonPath('cart.items.0.code', $product->barcode)
            ->assertJsonPath('is_verified', false);

        $this->postJson(route('pos.verify.check', $connection->json('verification_code')), [
            'code' => $product->barcode,
        ])
            ->assertOk()
            ->assertJsonPath('cart.all_items_checked', true)
            ->assertJsonPath('cart.is_verified', true);
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

        $checkout = $this->postJson(route('pos.checkout'))
            ->assertOk()
            ->assertJsonPath('message', 'Checkout completed.');

        $checkout->assertJsonPath(
            'receipt_url',
            route('sales.show', $checkout->json('sale_id')),
        );
        $this->get($checkout->json('receipt_url'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->barcode);

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('sales', [
            'subtotal' => 7,
            'total' => 7,
        ]);
    }

    public function test_customer_account_registration_owns_scan_and_go_carts(): void
    {
        $this->app['auth']->guard('customer')->logout();
        $this->get(route('shop.entry'))
            ->assertOk()
            ->assertSee('Sign In')
            ->assertSee('Create an account');

        $this->post(route('shop.register.store'), [
            'full_name' => 'Registered Shopper',
            'email' => 'registered@example.test',
            'phone' => '555-0101',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect(route('shop.entry'));

        $customer = Customer::query()->where('email', 'registered@example.test')->firstOrFail();
        $this->post(route('shop.start'))->assertRedirect();
        $cart = $customer->carts()->firstOrFail();
        $this->assertSame('Registered Shopper', $cart->customer_name);

        $this->app['auth']->guard('customer')->logout();
        $this->get(route('shop.cart', $cart->token))->assertRedirect(route('shop.entry'));
        $this->post(route('shop.login'), [
            'email' => 'REGISTERED@example.test',
            'password' => 'secure-pass-123',
        ])->assertRedirect(route('shop.cart', $cart->token));

        $otherCustomer = $this->signInShopper('Other Shopper');
        $this->get(route('shop.cart', $cart->token))->assertNotFound();
        $this->assertNotSame($customer->id, $otherCustomer->id);
    }

    public function test_staff_can_record_discount_id_and_monthly_quota_on_customer(): void
    {
        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Monthly store-discount quota')
            ->assertSee('Discount ID type');

        $this->post(route('customers.store'), [
            'full_name' => 'Discount Profile',
            'email' => 'discount-profile@example.test',
            'discount_id_type' => 'pwd',
            'monthly_discount_limit' => 25,
        ])->assertSessionHasErrors('discount_id_number');

        $this->post(route('customers.store'), [
            'full_name' => 'Discount Profile',
            'email' => 'discount-profile@example.test',
            'discount_id_type' => 'pwd',
            'discount_id_number' => 'PWD-250',
            'monthly_discount_limit' => 25,
            'store_discount_reminder' => 1,
        ])->assertRedirect(route('customers.index'));

        $customer = Customer::query()->where('email', 'discount-profile@example.test')->firstOrFail();
        $this->assertSame('pwd', $customer->discount_id_type);
        $this->assertSame('PWD-250', $customer->discount_id_number);
        $this->assertSame('25.00', $customer->monthly_discount_limit);
        $this->assertTrue($customer->store_discount_reminder);
    }

    public function test_store_discount_is_capped_by_monthly_quota_and_saved_per_item(): void
    {
        $customer = $this->signInShopper('Quota Shopper', [
            'monthly_discount_limit' => 5,
            'store_discount_reminder' => true,
        ]);
        $this->postJson(route('pos.customer.select'), ['customer_id' => $customer->id])->assertOk();
        $product = $this->product('QUOTA-ITEM-1', 3, 10.00);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();
        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'discount_type' => 'store',
            'discount_percent' => 50,
        ])->assertOk()
            ->assertJsonPath('cart.discount', 5)
            ->assertJsonPath('cart.total', 15);

        $checkout = $this->postJson(route('pos.checkout'))->assertOk();
        $sale = \App\Models\Sale::findOrFail($checkout->json('sale_id'));
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('Quota Shopper', $sale->customer_name);
        $this->assertSame('20.00', $sale->subtotal);
        $this->assertSame('5.00', $sale->discount);
        $this->assertSame('15.00', $sale->total);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'discount_type' => 'store',
            'discount_percent' => 50,
            'discount_amount' => 5,
            'line_total' => 15,
        ]);
        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('STORE 50.00%')
            ->assertSee('Quota Shopper');
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Purchase History')
            ->assertSee('Quota Shopper')
            ->assertSee('5.00 used');
        $this->getJson(route('customers.lookup', ['search' => 'Quota Shopper']))
            ->assertOk()
            ->assertJsonPath('customers.0.monthly_discount_used', 5)
            ->assertJsonPath('customers.0.monthly_discount_remaining', 0);
    }

    public function test_pwd_discount_requires_matching_customer_id_and_does_not_use_store_quota(): void
    {
        $customer = $this->signInShopper('PWD Shopper', [
            'discount_id_type' => 'pwd',
            'discount_id_number' => 'PWD-001',
            'monthly_discount_limit' => 0,
        ]);
        $this->postJson(route('pos.customer.select'), ['customer_id' => $customer->id])->assertOk();
        $product = $this->product('PWD-ITEM-1', 2, 20.00);
        $this->postJson(route('pos.scan'), ['code' => $product->barcode])->assertOk();

        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'discount_type' => 'senior',
            'discount_percent' => 10,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The customer does not have a recorded SENIOR ID.');

        $this->postJson(route('pos.cart.update'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'discount_type' => 'pwd',
            'discount_percent' => 10,
        ])->assertOk();
        $checkout = $this->postJson(route('pos.checkout'))->assertOk();
        $sale = \App\Models\Sale::findOrFail($checkout->json('sale_id'));
        $this->assertSame('2.00', $sale->discount);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'discount_type' => 'pwd',
            'discount_amount' => 2,
        ]);
        $this->getJson(route('customers.lookup', ['search' => 'PWD Shopper']))
            ->assertOk()
            ->assertJsonPath('customers.0.monthly_discount_used', 0);
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

    private function signInShopper(string $name, array $attributes = []): Customer
    {
        static $counter = 0;
        $counter++;
        $customer = Customer::create(array_merge([
            'full_name' => $name,
            'email' => 'shopper'.$counter.'@example.test',
            'password' => Hash::make('password-123'),
            'monthly_discount_limit' => 0,
        ], $attributes));
        $this->actingAs($customer, 'customer');
        $this->app['auth']->shouldUse('web');

        return $customer;
    }
}
