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

- **Products** is the inventory list. Use the search field to find a product by name, category, or barcode; filter to low stock (1–5 units) or out of stock. The summary counts active products and their on-hand units. Select **Edit / Restock** to update quantities or product details.
- Successful checkout deducts stock and saves a sale with a snapshot of each sold product's name, barcode, quantity, and price.
- Open **Sales Reports** to select a date range and review revenue, transaction count, units sold, top-selling items, and each completed transaction. Reports default to today.
- Select **View receipt** in the transaction list, or the receipt link shown after checkout, to view and print a receipt. Sold-item snapshots remain available if a product is renamed or later removed.

When printing the store-entry QR, use the **Phone-accessible store URL** field. A QR created while the cashier browser is at `localhost` or `127.0.0.1` points back to that device, not the phone. Paste the VS Code forwarded HTTPS URL with `/shop` at the end; camera access on phones requires HTTPS.

On **Products → Register Product**, use **Save & Register Next** to save an item to the shared product inventory, clear the form, and keep the cashier scanner ready for another item. The camera scanner pauses after a read and resumes after saving, preventing one label from being repeatedly added. **Connect Phone Online** creates a temporary QR session; scan it with the phone to open the mobile scanner, optionally take a product photo, then send multiple barcode or OCR/manual codes to the cashier form. The photo travels with its scanned code and is saved with the product. Customers see the saved product photo next to the name and price in their Scan & Go cart. The session expires after five minutes. Its QR initially uses the address currently open in the cashier's browser. For a phone on the same Wi-Fi, edit **URL the phone can reach** to use the cashier computer's LAN IP and the same port/path if needed. The web server must accept connections from the phone; camera scanning on a phone requires HTTPS. `localhost` and `127.0.0.1` on the QR are not the cashier computer when opened from another device.

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

The scanner uses the browser camera API through `html5-qrcode` (and `getUserMedia` for printed-number OCR). It shows permission/HTTPS-specific guidance when the camera cannot start and suppresses repeat camera decodes for 1.2 seconds. AJAX mutations already send the page's CSRF token in `X-CSRF-TOKEN`; keep the phone on the same forwarded HTTPS hostname so its session cookie and CSRF token remain valid. A different forwarding hostname requires a fresh login and page load.

## Customer Scan & Go

1. Sign in as a cashier/admin and open **POS Checkout → Store Entry QR**, or visit `/pos/store-qr`. Print or display the QR code.
2. The customer scans it, opens `/shop`, enters a name, and taps **Start Shopping**.
3. On the cart page, use **Scan Barcode with Camera**, **Read Printed Number / Can Code**, or type a code and tap **Add Code**. OCR puts the recognized code in the input for review before adding it.
4. On the phone-friendly cart page, change quantities or tap **Remove item** as needed. The page shows **Total Pieces** and **Running Grand Total**.
5. Tap **Finish & Generate Cashier QR**. The resulting QR opens the cashier-phone item scanner. The cashier POS can scan that same QR to load the cart, or the cart can be loaded by entering its displayed `CART-...` code.

Shopping carts expire after two hours. Product availability is checked while adding items, when the cashier imports the cart, and again during checkout.

## Cashier Import, Verification, and Payment

1. At `/pos`, scan the customer's final QR with the cashier screen camera or enter its `CART-...` code. The POS loads that cart without adding a sale and automatically displays the phone-check QR.
2. Before payment, the cashier may edit quantities, remove items, or scan additional products in the POS cart. Any change resets item checks.
3. Scan the displayed verification QR with the cashier's phone; it opens the online item-check screen. **Connect Cashier Phone** can regenerate it when needed.
4. On the phone, scan each physical item's barcode, use OCR for a printed number, enter a code manually, or tap **Check one**. Unknown, unexpected, and over-quantity scans show a mismatch warning. The phone refreshes its expected items when the cashier edits the cart.
5. Once every expected piece is checked, the mobile page and cashier POS show **SCANNED AND CHECKED ITEM - READY FOR PAYMENT**. The cashier POS polls for this state, and checkout stays disabled until verification is complete.
6. Tap **Complete Checkout**. Successful payment creates the sale, deducts stock, and marks the customer cart `paid` in the same database transaction. A paid cart cannot be imported again.

## Online Phone Scanning

- On **Products → Register Product**, select **Connect Phone Online** to display a reusable, five-minute QR session. The phone stays on the mobile scanner and submits each scanned product code to the cashier form; the cashier page restores the session when **Save & Register Next** opens a fresh form.
- On **POS Checkout**, scan the customer's final QR using the POS camera or enter its `CART-` code, review and edit the imported items, then scan the displayed phone-check QR with the cashier phone. **Connect Cashier Phone** can regenerate it when needed.
- Scan the verification QR with the cashier's phone. The mobile check page is online and scans each physical item against the expected cart.
- On the Products list, choose **Print QR** to display a product QR that encodes the same code.
- Cashier edits to a customer cart reset its verification progress. All pieces must be checked again before the POS enables checkout.
- The mobile camera scanner ignores repeat decodes of the same code for 1.2 seconds while accepting different items immediately. Manual code entry is available if camera access or OCR is unavailable.

## Routes

```text
GET    /shop
GET    /shop/cart/{token}
POST   /shop/cart/{token}/scan
PUT    /shop/cart/{token}/items/{item}
POST   /shop/cart/{token}/finalize
GET    /pos/store-qr
GET    /pos
POST   /pos/scan-item
POST   /pos/cart/update
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
GET    /products/phone-scan/{token}/result
```

Customer shop and mobile verification routes are accessed with their unique cart/verification codes. POS, store QR, cart import, and cashier status routes require authentication. POST/PUT routes use Laravel CSRF protection.
