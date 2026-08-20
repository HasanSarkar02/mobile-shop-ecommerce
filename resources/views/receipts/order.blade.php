@php
    $hasSerials = collect($receipt['items'])->contains(fn (array $item): bool => $item['serials'] !== []);
    $hasBilling = $receipt['customer']['billing_address'] !== [];
    $itemColumnCount = $hasSerials ? 7 : 6;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $receipt['order']['number'] }} - {{ $receipt['store']['name'] }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, Helvetica, sans-serif;
            --ink: #17202b;
            --muted: #687381;
            --line: #d9dee5;
            --accent: #16a34a;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; color: var(--ink); font-size: 13px; line-height: 1.35; }
        .receipt-shell { width: min(100% - 2rem, 880px); margin: 1rem auto; }
        .toolbar { display: flex; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
        .toolbar-actions { display: flex; gap: .5rem; }
        .button { border: 1px solid #cbd2da; border-radius: .35rem; background: #fff; color: var(--ink); cursor: pointer; padding: .45rem .75rem; font-size: .8rem; text-decoration: none; }
        .button-primary { border-color: var(--ink); background: var(--ink); color: #fff; }
        .receipt { background: #fff; padding: 1.35rem 1.5rem 1.1rem; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; border-bottom: 2px solid var(--ink); padding-bottom: .8rem; }
        .brand { min-width: 0; }
        .logo { display: block; max-width: 145px; max-height: 48px; object-fit: contain; object-position: left center; }
        .store-name { font-size: 1.2rem; font-weight: 700; letter-spacing: -.01em; }
        .store-contact { color: var(--muted); font-size: .75rem; line-height: 1.35; margin-top: .3rem; }
        .receipt-heading { text-align: right; }
        .receipt-heading h1 { font-size: 1.7rem; letter-spacing: .06em; line-height: 1; margin: 0; text-transform: uppercase; }
        .receipt-heading p { color: var(--muted); font-size: .78rem; margin: .2rem 0 0; }
        .meta-row { align-items: center; border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: .35rem 1.25rem; padding: .55rem 0; }
        .meta-item { display: inline-flex; gap: .3rem; white-space: nowrap; }
        .meta-label { color: var(--muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
        .status { background: #eef7f0; border-radius: 999px; color: #166534; font-size: .72rem; font-weight: 700; padding: .15rem .5rem; }
        .parties { border-bottom: 1px solid var(--line); display: grid; grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr); gap: 1.5rem; padding: .8rem 0; }
        .party-block { min-width: 0; }
        .party-block.billing { grid-column: 1 / -1; border-top: 1px solid var(--line); padding-top: .65rem; }
        .section-label { color: var(--accent); font-size: .7rem; font-weight: 700; letter-spacing: .09em; margin: 0 0 .25rem; text-transform: uppercase; }
        .party-lines { color: #2f3945; font-size: .8rem; line-height: 1.4; }
        .muted { color: var(--muted); }
        .section { margin-top: .85rem; }
        .section-heading { align-items: baseline; display: flex; justify-content: space-between; margin-bottom: .35rem; }
        .section-heading h2 { font-size: .78rem; letter-spacing: .09em; margin: 0; text-transform: uppercase; }
        .section-heading span { color: var(--muted); font-size: .72rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid var(--line); padding: .42rem .35rem; text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: .67rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        td { font-size: .8rem; }
        .right { text-align: right; }
        .product-name { font-weight: 700; }
        .secondary { color: var(--muted); display: block; font-size: .72rem; line-height: 1.3; margin-top: .1rem; }
        .serial { color: #344054; display: block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; line-height: 1.35; white-space: nowrap; }
        .table-wrap { overflow-x: auto; }
        .totals { margin: .7rem 0 0 auto; max-width: 330px; }
        .totals-row { align-items: baseline; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; gap: 1rem; padding: .24rem 0; }
        .grand-total { border-top: 2px solid var(--ink); border-bottom: 0; font-size: .98rem; font-weight: 700; margin-top: .25rem; padding-top: .45rem; }
        .due { color: #9a3412; font-weight: 700; }
        .payment-table th, .payment-table td { padding: .35rem; }
        .payment-table td { font-size: .75rem; }
        .payment-table .status-text { font-weight: 700; }
        .footer { border-top: 1px solid var(--line); color: var(--muted); font-size: .75rem; margin-top: .9rem; padding-top: .55rem; text-align: center; white-space: pre-line; }
        .footer-contact { margin-bottom: .2rem; }
        @media (max-width: 640px) {
            .receipt-shell { margin: 0; width: 100%; }
            .receipt { padding: 1rem; }
            .header { gap: .75rem; }
            .receipt-heading h1 { font-size: 1.35rem; }
            .parties { gap: .8rem; grid-template-columns: 1fr; }
            .party-block.billing { grid-column: auto; }
            .table-wrap { margin-right: -1rem; padding-right: 1rem; }
            table { min-width: 650px; }
        }
        @media print {
            @page { size: A4; margin: 9mm; }
            body { background: #fff; font-size: 11px; }
            .receipt-shell { margin: 0; width: 100%; }
            .receipt { padding: 0; }
            .no-print { display: none !important; }
            .section { break-inside: avoid; }
            .totals, .payment-table, .parties { break-inside: avoid; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            th, td { padding: .32rem .25rem; }
            .header { padding-bottom: .55rem; }
            .meta-row { padding: .4rem 0; }
            .parties { padding: .55rem 0; }
            .section { margin-top: .65rem; }
            .footer { margin-top: .65rem; }
            .button { display: none; }
        }
    </style>
</head>
<body>
    <main class="receipt-shell">
        <div class="toolbar no-print">
            <a class="button" href="{{ $backUrl }}">Back to Order</a>
            <div class="toolbar-actions">
                <button class="button button-primary" type="button" onclick="window.print()">Print Receipt</button>
            </div>
        </div>

        <article class="receipt">
            <header class="header">
                <div class="brand">
                    @if ($receipt['store']['logo_url'])
                        <img class="logo" src="{{ $receipt['store']['logo_url'] }}" alt="{{ $receipt['store']['name'] }}">
                    @endif
                    <div class="store-name">{{ $receipt['store']['name'] }}</div>
                    <div class="store-contact">
                        @if ($receipt['store']['phone']) <span>{{ $receipt['store']['phone'] }}</span> @endif
                        @if ($receipt['store']['phone'] && $receipt['store']['email']) <span> · </span> @endif
                        @if ($receipt['store']['email']) <span>{{ $receipt['store']['email'] }}</span> @endif
                        @if ($receipt['store']['address']) <div>{{ $receipt['store']['address'] }}</div> @endif
                    </div>
                </div>
                <div class="receipt-heading">
                    <h1>Receipt</h1>
                    <p>Order {{ $receipt['order']['number'] }}</p>
                    @if ($receipt['order']['invoice']) <p>Invoice {{ $receipt['order']['invoice'] }}</p> @endif
                    <p>{{ $receipt['order']['date'] }}</p>
                </div>
            </header>

            <div class="meta-row">
                <div class="meta-item"><span class="meta-label">Status</span><span class="status">{{ $receipt['order']['status'] }}</span></div>
                @if ($receipt['shipping']['method'])
                    <div class="meta-item"><span class="meta-label">Shipping</span><strong>{{ $receipt['shipping']['method'] }}</strong></div>
                @endif
                @if ($receipt['order']['customer_type'] !== 'Guest')
                    <div class="meta-item"><span class="meta-label">Customer</span><strong>{{ $receipt['order']['customer_type'] }}</strong></div>
                @endif
            </div>

            <section class="parties">
                <div class="party-block">
                    <p class="section-label">Customer</p>
                    <div class="party-lines">
                        @if ($receipt['customer']['name']) <div>{{ $receipt['customer']['name'] }}</div> @endif
                        @if ($receipt['customer']['phone']) <div>{{ $receipt['customer']['phone'] }}</div> @endif
                        @if ($receipt['customer']['email']) <div>{{ $receipt['customer']['email'] }}</div> @endif
                    </div>
                </div>
                @if ($receipt['customer']['shipping_address'] !== [])
                    <div class="party-block">
                        <p class="section-label">Delivery address</p>
                        <div class="party-lines">
                            @foreach ($receipt['customer']['shipping_address'] as $line)
                                <div>{{ $line['value'] }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($hasBilling)
                    <div class="party-block billing">
                        <p class="section-label">Billing address</p>
                        <div class="party-lines">
                            @foreach ($receipt['customer']['billing_address'] as $line)
                                <span>{{ $line['value'] }}</span>@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <section class="section">
                <div class="section-heading">
                    <h2>Items</h2>
                    <span>{{ count($receipt['items']) }} {{ count($receipt['items']) === 1 ? 'item' : 'items' }}</span>
                </div>
                <div class="table-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                @if ($hasSerials) <th>Serial / IMEI</th> @endif
                                <th>SKU</th>
                                <th class="right">Qty</th>
                                <th class="right">Unit price</th>
                                <th class="right">Discount</th>
                                <th class="right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipt['items'] as $item)
                                <tr>
                                    <td>
                                        <span class="product-name">{{ $item['product'] ?: '—' }}</span>
                                        @if ($item['variant']) <span class="secondary">Variant: {{ $item['variant'] }}</span> @endif
                                    </td>
                                    @if ($hasSerials)
                                        <td>
                                            @forelse ($item['serials'] as $serial)
                                                <span class="serial">{{ $serial }}</span>
                                            @empty
                                                <span class="muted">—</span>
                                            @endforelse
                                        </td>
                                    @endif
                                    <td>{{ $item['sku'] ?: '—' }}</td>
                                    <td class="right">{{ $item['quantity'] }}</td>
                                    <td class="right">{{ $item['unit_price'] }}</td>
                                    <td class="right">{{ $item['discount'] ?? '—' }}</td>
                                    <td class="right">{{ $item['line_total'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $itemColumnCount }}" class="muted">No items recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="totals">
                <div class="totals-row"><span>Subtotal</span><strong>{{ $receipt['totals']['subtotal'] }}</strong></div>
                <div class="totals-row"><span>Discount / coupon</span><strong>{{ $receipt['totals']['discount'] }}</strong></div>
                <div class="totals-row"><span>Shipping</span><strong>{{ $receipt['totals']['shipping'] }}</strong></div>
                <div class="totals-row"><span>Tax</span><strong>{{ $receipt['totals']['tax'] }}</strong></div>
                <div class="totals-row grand-total"><span>Grand total</span><strong>{{ $receipt['totals']['grand_total'] }}</strong></div>
                <div class="totals-row"><span>Amount paid</span><strong>{{ $receipt['totals']['amount_paid'] }}</strong></div>
                <div class="totals-row due"><span>Amount due</span><strong>{{ $receipt['totals']['amount_due'] }}</strong></div>
            </section>

            <section class="section">
                <div class="section-heading"><h2>Payment history</h2></div>
                @forelse ($receipt['payments'] as $payment)
                    <div class="table-wrap">
                        <table class="payment-table">
                            <thead>
                                <tr><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th><th class="right">Status</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $payment['date'] }}</td>
                                    <td>{{ $payment['method'] }}@if ($payment['provider']) <span class="secondary">{{ $payment['provider'] }}</span> @endif</td>
                                    <td>{{ $payment['reference'] ?: '—' }}</td>
                                    <td class="right">{{ $payment['amount'] }}</td>
                                    <td class="right status-text">{{ $payment['status'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="muted small">No payments recorded.</p>
                @endforelse
            </section>

            @if ($receipt['footer'] || $receipt['store']['phone'] || $receipt['store']['email'])
                <footer class="footer">
                    @if ($receipt['store']['phone'] || $receipt['store']['email'])
                        <div class="footer-contact">Support: {{ $receipt['store']['phone'] }}@if ($receipt['store']['phone'] && $receipt['store']['email']) · @endif{{ $receipt['store']['email'] }}</div>
                    @endif
                    @if ($receipt['footer']) <div>{{ $receipt['footer'] }}</div> @endif
                </footer>
            @endif
        </article>
    </main>
</body>
</html>
