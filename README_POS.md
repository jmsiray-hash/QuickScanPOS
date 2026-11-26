# Grocery POS Scan & Go

The POS supports a customer Scan & Go cart, cashier cart import, mobile item verification, printable product QR codes, barcode readers, and printed product codes.

## Setup

From the project directory:

```powershell
composer install
npm install
npm run build
php artisan migrate --seed
php artisan route:list --path=shop
php artisan route:list --path=pos
php artisan route:list --path=products
composer test
php artisan serve
```

`customer_carts` and `customer_cart_items` keep shopping and verification data separate from completed sales. A printed number/code for an item without a barcode can be entered into the product's **Barcode / Printed Product Code** field; the existing `products.barcode` column stores either value.

After registering a product, select **Print QR** from the Products list (or **Print Product QR** while editing). The printable QR contains that product's barcode/printed code, so both the POS and phone cart scanner can add it.

The product camera scanner, customer camera scanner, cashier phone scanner, QR codes, and printed-number OCR use CDN scripts and need an internet connection. Phone camera access requires HTTPS (or localhost). The cashier phone connects online by scanning the verification QR shown by the cashier POS; Bluetooth pairing is not required.

## Inventory, Completed Sales, and Receipts

- **Products** is the inventory list. Use the search field to find a product by name, category, or barcode; filter to low stock (1–5 units) or out of stock. The summary counts active products and their on-hand units. Select **Edit / Restock** to update quantities or product details, or **Remove** to delete a product after confirmation. Removal clears it from open carts and inventory, deletes its photo, and preserves completed sale item snapshots.
- **Cashier Dashboard** in the staff navigation shows available products as searchable, category-filtered cards. Enter a quantity and add an item to the POS cart; use **View cart / checkout** to review it in POS Checkout. The catalog dashboard omits inactive and out-of-stock products.
- Successful checkout deducts stock and saves a sale with a snapshot of each sold product's name, barcode, quantity, and price.
- Open **Sales Reports** to select a date range and review revenue, transaction count, units sold, top-selling items, and each completed transaction. Reports default to today.
- Select **View receipt** in the transaction list, or the receipt link shown after checkout, to view and print a receipt. Sold-item snapshots remain available if a product is renamed or later removed.
- Receipts offer 52 mm and 80 mm paper layouts for a compatible BLE thermal printer. On HTTPS in Chrome/Edge, choose **Connect Bluetooth Printer**, select the printer, then **Print to Bluetooth**. Classic Bluetooth printers or browsers without Web Bluetooth can use **Print Receipt** after pairing/installing the printer in the device's operating system; actual paper width is selected in that printer's settings.

When printing the store-entry QR, use the **Phone-accessible store URL** field. A QR created while the cashier browser is at `localhost` or `127.0.0.1` points back to that device, not the phone. Paste the VS Code forwarded HTTPS URL with `/shop` at the end; camera access on phones requires HTTPS.

On **Products → Register Product**, use **Save & Register Next** to save an item to the shared product inventory, clear the form, and keep the cashier scanner ready for another item. The camera scanner pauses after a read and resumes after saving, preventing one label from being repeatedly added. **Connect Phone Online** creates a temporary QR session. On the phone, capture a product photo, scan its code, enter its name, category, cost/selling prices, and starting stock, then tap **Save Product to Inventory**. The product and photo are saved directly to the shared inventory; the phone form is ready for the next item. Customers see the saved product photo next to the name and price in their Scan & Go cart. **Send Code to Cashier Desktop Instead** remains available when you only need to send a scan to the cashier form. The session expires after five minutes. Its QR initially uses the address currently open in the cashier's browser. For a phone on the same Wi-Fi, edit **URL the phone can reach** to use the cashier computer's LAN IP and the same port/path if needed. The web server must accept connections from the phone; camera scanning on a phone requires HTTPS. `localhost` and `127.0.0.1` on the QR are not the cashier computer when opened from another device.

## Local Smartphone Testing over an HTTPS Tunnel

Use the built Vite assets for tunnel testing so the browser does not try to load the Vite development server from `localhost:5173`.

1. In `.env`, keep `TRUSTED_PROXIES=127.0.0.1,::1` when the forwarding agent connects from loopback. Keep `SESSION_DOMAIN` blank (host-only cookie), set `SESSION_SAME_SITE=lax`, and set `SESSION_SECURE_COOKIE=true` while testing through HTTPS. Reset it to blank for plain HTTP localhost testing.
2. Stop any running Vite dev server. From PowerShell, clear a stale Vite hot-file marker, build assets, and start the app:

   ```powershell
   cd C:\xampp\htdocs\LaravelCompletePOS\TFA8_POS_Complete
   if (Test-Path public\hot) { Remove-Item public\hot }
   npm run build
   php artisan config:clear
   php artisan serve --host=127.0.0.1 --port=8000
   ```

3. In VS Code, open the **Ports** panel, forward port `8000`, and open/copy its HTTPS forwarded URL. If the forwarded request does not originate from loopback, set `TRUSTED_PROXIES` to the forwarding process's actual IP (avoid `*` unless this is an isolated local-only setup). Set `APP_URL` to this HTTPS URL if URLs are generated outside a web request. If you change `.env`, restart `php artisan serve` and run `php artisan config:clear`.
4. Open that same HTTPS forwarded URL on both the cashier computer and phone, then sign in on the cashier page. Do not mix the tunnel URL with `localhost` in the same workflow; they are separate browser origins and have separate login/session cookies.
5. To use the printed Store Entry QR, open **POS Checkout → Store Entry QR** on the cashier computer and paste `<forwarded-HTTPS-URL>/shop` into **Phone-accessible store URL**. Scan the refreshed QR from the phone. Alternatively, open the HTTPS `/shop` URL directly. Start a cart, tap **Scan Barcode with Camera**, allow camera access, and scan a product QR/barcode. Confirm the item appears in the cart and its quantity/total update. The mobile item verification page and product phone-scanner page use the same camera component.

The scanner uses the browser camera API through `html5-qrcode` (and `getUserMedia` for printed-number OCR). Barcode and QR decoding runs at 15 frames per second with a responsive scan window up to 90% of the camera view width; the desktop and phone scanner previews expand to the available width. It shows permission/HTTPS-specific guidance when the camera cannot start and suppresses repeat camera decodes for 1.2 seconds. AJAX mutations already send the page's CSRF token in `X-CSRF-TOKEN`; keep the phone on the same forwarded HTTPS hostname so its session cookie and CSRF token remain valid. A different forwarding hostname requires a fresh login and page load.

## Customer Scan & Go

1. Sign in as a cashier/admin and open **POS Checkout → Store Entry QR**, or visit `/pos/store-qr`. Print or display the QR code.
2. The customer scans it and opens `/shop`. The visit appears immediately in the staff **Scan & Go Customers** activity list. Sign-in is optional: choose **Start Shopping as Guest** to shop without an account, or sign in/create an account to link purchases to customer history.
3. On the cart page, use **Scan Barcode with Camera**, **Read Printed Number / Can Code**, or type a code and tap **Add Code**. OCR puts the recognized code in the input for review before adding it.
4. On the phone-friendly cart page, change quantities or tap **Remove item** as needed. The page shows **Total Pieces** and **Running Grand Total**.
5. Tap **Finish & Generate Cashier QR**. The resulting QR opens the cashier-phone item scanner. The cashier POS can scan that same QR to load the cart, or the cart can be loaded by entering its displayed `CART-...` code.

Shopping carts expire after two hours. Account-linked carts require that customer to be signed in; guest carts use their unique cart link. The staff activity page updates current cart items while customers scan. After payment, the customer's phone shows a paid, itemized receipt and a one-time service rating form. Product availability is checked while adding items, when the cashier imports the cart, and again during checkout.

## Store Settings and Customer Activity

Admins can open **Settings** in the staff navigation to set the company/store name and address. The values are saved with each sale and appear on the cashier receipt, printable receipt, and the customer's phone after payment. **Scan & Go Customers** lists visits as soon as the entrance QR opens; staff can select a visitor to see cart items and totals update while they shop.

## Cashier Import, Verification, and Payment

1. At `/pos`, open a shift and record the opening cash fund before completing a transaction. The cashier QR camera can then scan the customer's final QR or enter its `CART-...` code. The POS loads that cart without adding a sale.
2. Search by customer name, email, or phone to select a customer for a direct POS sale. The POS shows the customer's recorded PWD/Senior ID, store-discount reminder, monthly store-discount quota used, and remaining amount. Scan & Go customers are linked automatically.
3. Before payment, edit quantities, scan products, or enter a lower promo price for an item. A promo price is saved as a `PROMO` line and shown separately on the receipt. Any item/quantity change resets optional item checks.
4. For a Senior/PWD discount, select the type, enter the ID number, and apply it only to catalog items marked Senior/PWD eligible. Eligible lines receive the configured 20% discount and VAT exemption; promo-priced items do not stack another discount. Set eligible products in **Products → Edit / Restock** only after confirming the applicable BIR rules for the product.
5. Choose **Cash**, **GCash**, **Maya**, or **Card**. Cash requires the amount received and shows change; GCash/Maya require the transaction reference. A successful sale stores payment, tax, cashier, item, discount, and receipt details and deducts stock.
6. Optional queue/item check: scan the displayed verification QR with the cashier's phone, or use **Connect Cashier Phone**. It opens the online item-check screen. This physical-item check is optional and is separate from the POS cashier's customer-cart QR camera.
7. Completed receipts remain available in **Sales Reports**. An Admin or Manager can void a completed transaction by entering a reason; the sale is marked voided and its remaining product stock is restored. Voided cash sales are included as cash refunds in shift reconciliation.
8. At closing, open **Sales Reports**, count the drawer, and save the Z-reading. Expected cash is opening fund + cash sales − cash refunds/voids. Review the saved cash, GCash, Maya, card, counted cash, and over/short figures.

The POS treats listed prices as VAT-inclusive when `POS_VAT_RATE` is greater than zero; the default is 0% to preserve existing behavior. Configure the rate only after confirming the store's tax setup. Existing users default to Cashier; the account `gsiray49@gmail.com` is promoted to Admin by the SOP migration, and Admins can assign Cashier, Manager, or Admin roles in **Users**.

## Online Phone Scanning

- On **Products → Register Product**, select **Connect Phone Online** to display a reusable, five-minute QR session. The phone stays on the mobile scanner and submits each scanned product code to the cashier form; the cashier page restores the session when **Save & Register Next** opens a fresh form.
- On **POS Checkout**, scan the customer's final QR using the POS camera or enter its `CART-` code, review and edit the imported items, then check out. If queue lines are long, scan the displayed phone-check QR with the cashier phone for an optional item check. **Connect Cashier Phone** can regenerate it when needed.
- Scan the verification QR with the cashier's phone. The mobile check page is online and scans each physical item against the expected cart.
- On the Products list, choose **Print QR** to display a product QR that encodes the same code.
- Cashier edits to a customer cart reset its mobile verification progress. If you use the phone check, all pieces must be checked again before the POS shows the payment-ready verification status.
- The mobile camera scanner ignores repeat decodes of the same code for 1.2 seconds while accepting different items immediately. Manual code entry is available if camera access or OCR is unavailable.

## Routes

```text
GET    /shop
POST   /shop/login
GET    /shop/register
POST   /shop/register
POST   /shop/logout
GET    /shop/cart/{token}
GET    /customer-activity
GET    /customer-activity/{customerCart}
GET    /settings/store
PUT    /settings/store
POST   /shop/cart/{token}/scan
PUT    /shop/cart/{token}/items/{item}
POST   /shop/cart/{token}/finalize
GET    /pos/store-qr
GET    /pos
POST   /pos/scan-item
POST   /pos/cart/update
GET    /api/customers/lookup
POST   /pos/customer
POST   /pos/checkout
GET    /sales
GET    /sales/{sale}
GET    /pos/import-cart/{token}
POST   /pos/verification
GET    /pos/verify/{code}
POST   /pos/verify/{code}/check-item
GET    /pos/verify-status/{code}
GET    /products/{product}/qr
POST   /products/phone-scan
GET    /mobile-product-scan/{token}
POST   /mobile-product-scan/{token}
POST   /mobile-product-scan/{token}/save
GET    /products/phone-scan/{token}/result
```

Customer shopping can be used without login through the unique guest cart link; account-linked carts require the matching signed-in customer. Cashier phone verification uses its unique verification code. POS, store QR, cart import, customer lookup, and cashier status routes require staff authentication. POST/PUT routes use Laravel CSRF protection.
