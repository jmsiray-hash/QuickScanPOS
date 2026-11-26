@extends('layouts.app')

@section('title', 'Sale #'.$sale->id.' Receipt | QuickScanPos')

@section('content')
    <header class="receipt-store-header">
        <h2>{{ $sale->store_name ?: $storeSettings->store_name }}</h2>
        <p>{{ $sale->store_address ?: $storeSettings->store_address }}</p>
    </header>
    <div class="page-actions receipt-actions">
        <h2>Sale Receipt #{{ $sale->id }}</h2>
        <div class="row-actions">
            <a class="button button-secondary" href="{{ route('sales.index') }}">Back to Sales</a>
            <label for="receipt-paper-width">Paper</label>
            <select id="receipt-paper-width">
                <option value="52">52 mm</option>
                <option value="80" selected>80 mm</option>
            </select>
            <button class="button button-secondary" id="connect-bluetooth-printer" type="button">Connect Bluetooth Printer</button>
            <button class="button" id="print-bluetooth-receipt" type="button" disabled>Print to Bluetooth</button>
            <button class="button button-secondary" type="button" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
    <p id="printer-status" class="printer-status" role="status">Connect a compatible BLE thermal printer, or use Print Receipt for a printer installed in your device.</p>
    <dl class="receipt-details">
        <div><dt>Date</dt><dd>{{ $sale->created_at->format('M j, Y g:i A') }}</dd></div>
        <div><dt>Cashier</dt><dd>{{ $sale->user?->name ?? 'Deleted user' }}</dd></div>
        <div><dt>Sale number</dt><dd>#{{ $sale->id }}</dd></div>
        <div><dt>Customer</dt><dd>{{ $sale->customer_name ?: 'Walk-in' }}</dd></div>
        <div><dt>Payment</dt><dd>{{ strtoupper($sale->payment_method) }}</dd></div>
        @if ($sale->payment_reference)<div><dt>Payment reference</dt><dd>{{ $sale->payment_reference }}</dd></div>@endif
        @if ($sale->cash_received !== null)<div><dt>Cash received</dt><dd>₱{{ number_format($sale->cash_received, 2) }}</dd></div><div><dt>Change</dt><dd>₱{{ number_format($sale->change_due, 2) }}</dd></div>@endif
        @if ($sale->special_discount_type)<div><dt>{{ strtoupper($sale->special_discount_type) }} ID</dt><dd>{{ $sale->special_discount_id_number }}</dd></div>@endif
    </dl>
    @if ($sale->status === 'voided')
        <div class="void-receipt-banner">VOIDED by {{ $sale->voidedBy?->name ?? 'Deleted user' }} on {{ $sale->voided_at?->format('M j, Y g:i A') }}. Reason: {{ $sale->void_reason }}</div>
    @endif
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Barcode</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Line total</th></tr></thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $item->product_name }} @if($item->is_promo)<strong class="promo-tag">PROMO</strong>@endif</td>
                        <td>{{ $item->barcode }}</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>@if($item->is_promo)<del>₱{{ number_format($item->unit_price, 2) }}</del><br>₱{{ number_format($item->promo_unit_price, 2) }}@else₱{{ number_format($item->unit_price, 2) }}@endif</td>
                        <td>
                            @if ($item->discount_amount > 0)
                                {{ strtoupper($item->discount_type) }} {{ number_format($item->discount_percent, 2) }}% (−₱{{ number_format($item->discount_amount, 2) }})
                            @else
                                —
                            @endif
                        </td>
                        <td>₱{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="5">Subtotal</th><td>₱{{ number_format($sale->subtotal, 2) }}</td></tr>
                <tr><th colspan="5">Tax</th><td>₱{{ number_format($sale->tax, 2) }}</td></tr>
                <tr><th colspan="5">Discount</th><td>₱{{ number_format($sale->discount, 2) }}</td></tr>
                <tr><th colspan="5">Total</th><td><strong>₱{{ number_format($sale->total, 2) }}</strong></td></tr>
            </tfoot>
        </table>
    </div>
    <p class="receipt-thanks">Thank you for shopping with us.</p>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const receipt = @json($receiptData);
            const connectButton = document.getElementById('connect-bluetooth-printer');
            const printButton = document.getElementById('print-bluetooth-receipt');
            const paperSize = document.getElementById('receipt-paper-width');
            const status = document.getElementById('printer-status');
            let printerDevice = null;
            let printerCharacteristic = null;

            function setStatus(message, isError = false) {
                status.textContent = message;
                status.className = 'printer-status ' + (isError ? 'printer-error' : 'printer-success');
            }

            function ascii(value) {
                return String(value ?? '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^\x20-\x7E]/g, '?');
            }

            function wrapText(value, width) {
                const words = ascii(value).split(/\s+/);
                const lines = [];
                let line = '';
                for (const word of words) {
                    if (!word) continue;
                    if (word.length > width) {
                        if (line) lines.push(line);
                        line = '';
                        for (let offset = 0; offset < word.length; offset += width) {
                            lines.push(word.slice(offset, offset + width));
                        }
                    } else if (line && line.length + word.length + 1 > width) {
                        lines.push(line);
                        line = word;
                    } else {
                        line = line ? line + ' ' + word : word;
                    }
                }
                if (line) lines.push(line);
                return lines;
            }

            function columns(left, right, width) {
                const leftWidth = Math.max(0, width - right.length - 1);
                return ascii(left).slice(0, leftWidth).padEnd(leftWidth) + ' ' + right;
            }

            function makeReceiptBytes() {
                const width = Number(paperSize.value) === 52 ? 32 : 48;
                const rule = '-'.repeat(width);
                const lines = [
                    '\x1B@',
                    '\x1Ba\x01',
                    '\x1BE\x01',
                    ascii(receipt.store_name || 'QuickScanPOS'),
                    ...wrapText(receipt.store_address || '', width),
                    'SALES RECEIPT',
                    '\x1BE\x00',
                    '\x1Ba\x00',
                    '',
                    'Sale #' + receipt.id,
                    receipt.created_at,
                    'Cashier: ' + ascii(receipt.cashier),
                    receipt.customer_name ? 'Customer: ' + ascii(receipt.customer_name) : '',
                    'Payment: ' + ascii(String(receipt.payment_method || '').toUpperCase()),
                    receipt.special_discount_type ? ascii(String(receipt.special_discount_type).toUpperCase() + ' ID: ' + receipt.special_discount_id_number) : '',
                    receipt.payment_reference ? 'Reference: ' + ascii(receipt.payment_reference) : '',
                    receipt.cash_received !== null ? 'Cash received: PHP ' + Number(receipt.cash_received).toFixed(2) : '',
                    receipt.cash_received !== null ? 'Change: PHP ' + Number(receipt.change_due).toFixed(2) : '',
                    rule,
                ];

                for (const item of receipt.items) {
                    lines.push(...wrapText(item.name + (item.is_promo ? ' [PROMO]' : ''), width));
                    lines.push(columns(
                        item.quantity + ' x PHP ' + Number(item.promo_unit_price ?? item.unit_price).toFixed(2),
                        Number(item.line_total).toFixed(2),
                        width,
                    ));
                    if (item.discount_amount > 0) {
                        lines.push(columns(
                            (item.discount_type || 'discount').toUpperCase() + ' ' + Number(item.discount_percent).toFixed(2) + '%',
                            '-' + Number(item.discount_amount).toFixed(2),
                            width,
                        ));
                    }
                }

                lines.push(
                    rule,
                    columns('Subtotal', 'PHP ' + Number(receipt.subtotal).toFixed(2), width),
                    columns('Tax', 'PHP ' + Number(receipt.tax).toFixed(2), width),
                    columns('Discount', 'PHP ' + Number(receipt.discount).toFixed(2), width),
                    '\x1BE\x01',
                    columns('TOTAL', 'PHP ' + Number(receipt.total).toFixed(2), width),
                    '\x1BE\x00',
                    rule,
                    '',
                    '\x1Ba\x01',
                    'Thank you for shopping!',
                    '\x1Ba\x00',
                    '\x1Bd\x03',
                );

                return new TextEncoder().encode(lines.join('\n') + '\n');
            }

            function onPrinterDisconnected() {
                printerDevice = null;
                printerCharacteristic = null;
                printButton.disabled = true;
                setStatus('Printer disconnected. Connect it again before printing.', true);
            }

            connectButton.addEventListener('click', async () => {
                if (!navigator.bluetooth) {
                    setStatus('Bluetooth printing requires Chrome or Edge over HTTPS and a BLE thermal printer. Pair a classic Bluetooth printer with your device, then use Print Receipt.', true);
                    return;
                }

                connectButton.disabled = true;
                setStatus('Choose your BLE thermal printer in the browser pairing window...');
                try {
                    const device = await navigator.bluetooth.requestDevice({
                        acceptAllDevices: true,
                        optionalServices: [
                            '000018f0-0000-1000-8000-00805f9b34fb',
                            '0000ffe0-0000-1000-8000-00805f9b34fb',
                            '0000ffe5-0000-1000-8000-00805f9b34fb',
                            '0000ff00-0000-1000-8000-00805f9b34fb',
                            '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
                        ],
                    });
                    if (!device.gatt) throw new Error('This device does not provide a BLE GATT connection.');
                    device.addEventListener('gattserverdisconnected', onPrinterDisconnected);
                    const server = await device.gatt.connect();
                    const services = await server.getPrimaryServices();
                    let writableCharacteristic = null;

                    for (const service of services) {
                        const characteristics = await service.getCharacteristics();
                        writableCharacteristic = characteristics.find(characteristic =>
                            characteristic.properties.write || characteristic.properties.writeWithoutResponse,
                        ) || null;
                        if (writableCharacteristic) break;
                    }

                    if (!writableCharacteristic) {
                        device.gatt.disconnect();
                        throw new Error('No writable BLE printer service was found. Pair classic Bluetooth printers with your device and use Print Receipt.');
                    }

                    printerDevice = device;
                    printerCharacteristic = writableCharacteristic;
                    printButton.disabled = false;
                    setStatus('Connected to ' + (device.name || 'Bluetooth printer') + '. Select 52 mm or 80 mm, then print.');
                } catch (error) {
                    setStatus(error.message || 'Could not connect to the Bluetooth printer.', true);
                } finally {
                    connectButton.disabled = false;
                }
            });

            printButton.addEventListener('click', async () => {
                if (!printerCharacteristic || !printerDevice?.gatt?.connected) {
                    onPrinterDisconnected();
                    return;
                }

                printButton.disabled = true;
                setStatus('Sending receipt to printer...');
                try {
                    const bytes = makeReceiptBytes();
                    const chunkSize = 20;
                    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
                        const chunk = bytes.slice(offset, offset + chunkSize);
                        if (printerCharacteristic.properties.writeWithoutResponse) {
                            await printerCharacteristic.writeValueWithoutResponse(chunk);
                            await new Promise(resolve => window.setTimeout(resolve, 12));
                        } else if (printerCharacteristic.writeValueWithResponse) {
                            await printerCharacteristic.writeValueWithResponse(chunk);
                        } else {
                            await printerCharacteristic.writeValue(chunk);
                        }
                    }
                    setStatus('Receipt sent to ' + (printerDevice.name || 'Bluetooth printer') + ' (' + paperSize.value + ' mm).');
                } catch (error) {
                    setStatus(error.message || 'The receipt could not be sent to the printer.', true);
                } finally {
                    printButton.disabled = !printerCharacteristic;
                }
            });
        });
    </script>

    <style>
        .receipt-store-header { margin-bottom:18px; text-align:center; }
        .receipt-store-header h2 { margin:0; }
        .receipt-store-header p { margin:4px 0; white-space:pre-line; }
        .receipt-actions { align-items:center; flex-wrap:wrap; gap:8px; }
        .receipt-actions select { border:1px solid #94a3b8; border-radius:5px; font:inherit; padding:8px; }
        .printer-status { background:#f1f5f9; border-radius:6px; padding:10px 12px; }
        .printer-success { background:#dcfce7; color:#166534; }
        .printer-error { background:#fee2e2; color:#991b1b; }
        .receipt-details { display:grid; gap:16px; grid-template-columns:repeat(4,minmax(0,1fr)); margin:24px 0; }
        .receipt-details dt { color:var(--muted); margin:0; }
        .receipt-details dd { font-weight:700; }
        .receipt-thanks { color:var(--muted); margin-top:28px; text-align:center; }
        .promo-tag { background:#fef3c7; border-radius:999px; color:#92400e; font-size:.72rem; padding:2px 6px; }
        .void-receipt-banner { background:#fee2e2; border-radius:6px; color:#991b1b; margin:14px 0; padding:12px; }
        @media print {
            body { background:#fff; }
            .site-header,.site-shell>footer,.receipt-actions,.printer-status { display:none !important; }
            .site-shell { max-width:none; padding:0; }
            main { border:0; padding:0; }
            table { min-width:0; }
        }
        @media(max-width:640px) { .receipt-details { grid-template-columns:1fr; } }
    </style>
@endsection
