@php
    $hasSerials = collect($receipt['items'])->contains(fn (array $item): bool => $item['serials'] !== []);
    $hasBilling = $receipt['customer']['billing_address'] !== [];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $receipt['order']['number'] }} - {{ $receipt['store']['name'] }}</title>
    <style>
        @font-face {
            font-family: 'solaimanlipi';
            src: url('{{ storage_path('fonts/SolaimanLipi.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'solaimanlipi', sans-serif; font-size: 10pt; color: #17202b; line-height: 1.4; }
        .header { width: 100%; border-bottom: 2px solid #17202b; padding-bottom: 10px; margin-bottom: 10px; }
        .header-table { width: 100%; }
        .brand-name { font-size: 16pt; font-weight: bold; color: #17202b; }
        .store-contact { font-size: 8pt; color: #687381; margin-top: 4px; }
        .invoice-title { text-align: right; }
        .invoice-title h1 { font-size: 18pt; margin: 0; text-transform: uppercase; letter-spacing: 1px; color: #17202b; }
        .invoice-title p { font-size: 8pt; color: #687381; margin: 2px 0 0; }
        .meta { width: 100%; border-bottom: 1px solid #d9dee5; padding: 8px 0; font-size: 8pt; }
        .meta td { padding: 2px 10px 2px 0; }
        .meta-label { color: #687381; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.5px; }
        .status { background: #eef7f0; color: #166534; font-weight: bold; padding: 2px 8px; border-radius: 10px; font-size: 8pt; }
        .parties { width: 100%; border-bottom: 1px solid #d9dee5; padding: 10px 0; }
        .party { vertical-align: top; width: 50%; padding-right: 10px; }
        .section-label { color: #16a34a; font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .party-lines { font-size: 8.5pt; color: #2f3945; line-height: 1.5; }
        .billing { border-top: 1px solid #d9dee5; padding-top: 8px; margin-top: 8px; }
        .section { margin-top: 12px; }
        .section-heading { font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; border-bottom: 1px solid #d9dee5; padding-bottom: 4px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f8fafc; color: #687381; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 6px; border-bottom: 1px solid #d9dee5; text-align: left; }
        table.items td { font-size: 8.5pt; padding: 6px 6px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        .right { text-align: right; }
        .product-name { font-weight: bold; }
        .secondary { color: #687381; font-size: 7pt; display: block; margin-top: 2px; }
        .serial { font-family: monospace; font-size: 7pt; color: #344054; }
        .totals { width: 45%; margin-left: auto; margin-top: 10px; }
        .totals table { width: 100%; }
        .totals td { padding: 3px 0; font-size: 8.5pt; border-bottom: 1px solid #eef2f7; }
        .totals .grand { border-top: 2px solid #17202b; border-bottom: none; font-weight: bold; font-size: 10pt; padding-top: 6px; }
        .totals .due { color: #9a3412; font-weight: bold; }
        table.payments { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.payments th { background: #f8fafc; font-size: 7pt; text-transform: uppercase; padding: 4px 6px; border-bottom: 1px solid #d9dee5; text-align: left; }
        table.payments td { font-size: 8pt; padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
        .footer { margin-top: 15px; border-top: 1px solid #d9dee5; padding-top: 8px; text-align: center; font-size: 7.5pt; color: #687381; }
        .logo { max-width: 130px; max-height: 45px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if (! empty($receipt['store']['logo_base64']))
                    <img class="logo" src="{{ $receipt['store']['logo_base64'] }}" alt="{{ $receipt['store']['name'] }}">
                @elseif (! empty($receipt['store']['logo_absolute']) && file_exists($receipt['store']['logo_absolute']))
                    <img class="logo" src="{{ $receipt['store']['logo_absolute'] }}" alt="{{ $receipt['store']['name'] }}">
                @endif
                <div class="brand-name">{{ $receipt['store']['name'] }}</div>
                <div class="store-contact">
                    @if ($receipt['store']['phone']) {{ $receipt['store']['phone'] }} @endif
                    @if ($receipt['store']['phone'] && $receipt['store']['email']) · @endif
                    @if ($receipt['store']['email']) {{ $receipt['store']['email'] }} @endif
                    @if ($receipt['store']['website']) <br>{{ $receipt['store']['website'] }} @endif
                </div>
            </td>
            <td class="invoice-title" style="vertical-align: top;">
                <h1>Invoice</h1>
                <p>Order {{ $receipt['order']['number'] }}</p>
                @if ($receipt['order']['invoice']) <p>INV {{ $receipt['order']['invoice'] }}</p> @endif
                <p>{{ $receipt['order']['date'] }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td><span class="meta-label">Status</span> <span class="status">{{ $receipt['order']['status'] }}</span></td>
            @if ($receipt['shipping']['method'])
                <td><span class="meta-label">Shipping</span> <strong>{{ $receipt['shipping']['method'] }}</strong></td>
            @endif
            <td><span class="meta-label">Customer</span> <strong>{{ $receipt['order']['customer_type'] }}</strong></td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td class="party">
                <div class="section-label">Customer</div>
                <div class="party-lines">
                    @if ($receipt['customer']['name']) <div>{{ $receipt['customer']['name'] }}</div> @endif
                    @if ($receipt['customer']['phone']) <div>{{ $receipt['customer']['phone'] }}</div> @endif
                    @if ($receipt['customer']['email']) <div>{{ $receipt['customer']['email'] }}</div> @endif
                </div>
            </td>
            <td class="party">
                <div class="section-label">Delivery address</div>
                <div class="party-lines">
                    @if ($receipt['customer']['shipping_address'] !== [])
                        @foreach ($receipt['customer']['shipping_address'] as $line)
                            <div>{{ $line['value'] }}</div>
                        @endforeach
                    @else
                        <span style="color:#9ca3af;">—</span>
                    @endif
                </div>
            </td>
        </tr>
        @if ($hasBilling)
            <tr>
                <td colspan="2">
                    <div class="billing">
                        <div class="section-label">Billing address</div>
                        <div class="party-lines">
                            @foreach ($receipt['customer']['billing_address'] as $line)
                                <span>{{ $line['value'] }}</span>@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                </td>
            </tr>
        @endif
    </table>

    <div class="section">
        <div class="section-heading">Items — {{ count($receipt['items']) }} {{ count($receipt['items']) === 1 ? 'item' : 'items' }}</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 35%;">Product</th>
                    @if ($hasSerials) <th style="width: 18%;">Serial / IMEI</th> @endif
                    <th>SKU</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit price</th>
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
                                    <span style="color:#9ca3af;">—</span>
                                @endforelse
                            </td>
                        @endif
                        <td>{{ $item['sku'] ?: '—' }}</td>
                        <td class="right">{{ $item['quantity'] }}</td>
                        <td class="right">{{ $item['unit_price'] }}</td>
                        <td class="right">{{ $item['line_total'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $hasSerials ? 6 : 5 }}" style="color:#9ca3af;">No items recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right"><strong>{{ $receipt['totals']['subtotal'] }}</strong></td></tr>
        <tr><td>Discount</td><td class="right"><strong>{{ $receipt['totals']['discount'] }}</strong></td></tr>
        <tr><td>Shipping</td><td class="right"><strong>{{ $receipt['totals']['shipping'] }}</strong></td></tr>
        <tr><td>Tax</td><td class="right"><strong>{{ $receipt['totals']['tax'] }}</strong></td></tr>
        <tr class="grand"><td>Grand total</td><td class="right">{{ $receipt['totals']['grand_total'] }}</td></tr>
        <tr><td>Amount paid</td><td class="right">{{ $receipt['totals']['amount_paid'] }}</td></tr>
        <tr><td class="due">Amount due</td><td class="right">{{ $receipt['totals']['amount_due'] }}</td></tr>
    </table>

    <div class="section">
        <div class="section-heading">Payment history</div>
        @forelse ($receipt['payments'] as $payment)
            <table class="payments">
                <thead>
                    <tr><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th><th class="right">Status</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $payment['date'] }}</td>
                        <td>{{ $payment['method'] }} @if ($payment['provider']) <span class="secondary">{{ $payment['provider'] }}</span> @endif</td>
                        <td>{{ $payment['reference'] ?: '—' }}</td>
                        <td class="right">{{ $payment['amount'] }}</td>
                        <td class="right"><strong>{{ $payment['status'] }}</strong></td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p style="color:#9ca3af; font-size:8pt;">No payments recorded.</p>
        @endforelse
    </div>

    @if ($receipt['footer'] || $receipt['store']['phone'] || $receipt['store']['email'])
        <div class="footer">
            @if ($receipt['store']['phone'] || $receipt['store']['email'])
                <div>Support: {{ $receipt['store']['phone'] }}@if ($receipt['store']['phone'] && $receipt['store']['email']) · @endif{{ $receipt['store']['email'] }}</div>
            @endif
            @if ($receipt['footer']) <div style="white-space: pre-line; margin-top:4px;">{{ $receipt['footer'] }}</div> @endif
        </div>
    @endif
</body>
</html>
