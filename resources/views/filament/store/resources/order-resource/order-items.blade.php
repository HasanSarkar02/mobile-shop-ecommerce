<style>
    .fi-order-items { color: #374151; font-size: 0.875rem; }
    .fi-order-items-table-wrap { overflow-x: auto; width: 100%; }
    .fi-order-items-table { border-collapse: collapse; table-layout: fixed; min-width: 720px; width: 100%; }
    .fi-order-items-table th,
    .fi-order-items-table td { border-bottom: 1px solid #e5e7eb; padding: 0.75rem 0.5rem; text-align: left; vertical-align: top; }
    .fi-order-items-table th { color: #6b7280; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
    .fi-order-items-table th:nth-child(1) { width: 46%; }
    .fi-order-items-table th:nth-child(2) { width: 18%; }
    .fi-order-items-table th:nth-child(3) { width: 9%; }
    .fi-order-items-table th:nth-child(4) { width: 14%; }
    .fi-order-items-table th:nth-child(5) { width: 13%; }
    .fi-order-items-table .fi-order-items-number { text-align: right; white-space: nowrap; }
    .fi-order-items-product { align-items: flex-start; display: flex; gap: 0.75rem; min-width: 0; }
    .fi-order-items-image { border: 1px solid #e5e7eb; border-radius: 0.5rem; display: block; flex: 0 0 48px; height: 48px; object-fit: cover; width: 48px; }
    .fi-order-items-product-name { color: #111827; font-weight: 600; min-width: 0; overflow-wrap: anywhere; }
    .fi-order-items-sku { color: #6b7280; overflow-wrap: anywhere; }
    .fi-order-items-price { color: #374151; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fi-order-items-total { color: #111827; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .dark .fi-order-items { color: #d1d5db; }
    .dark .fi-order-items-table th,
    .dark .fi-order-items-table td { border-color: rgba(255, 255, 255, 0.1); }
    .dark .fi-order-items-table th,
    .dark .fi-order-items-sku { color: #9ca3af; }
    .dark .fi-order-items-product-name,
    .dark .fi-order-items-total { color: #f9fafb; }
    @media (max-width: 720px) {
        .fi-order-items-table { min-width: 640px; }
        .fi-order-items-table th,
        .fi-order-items-table td { padding: 0.625rem 0.375rem; }
    }
</style>

<div class="fi-order-items">
    <div class="fi-order-items-table-wrap">
        <table class="fi-order-items-table">
            <colgroup>
                <col style="width: 46%">
                <col style="width: 18%">
                <col style="width: 9%">
                <col style="width: 14%">
                <col style="width: 13%">
            </colgroup>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="fi-order-items-number">Qty</th>
                    <th class="fi-order-items-number">Unit Price</th>
                    <th class="fi-order-items-number">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($getState() as $item)
                    @php($image = $item->variant?->getFirstMediaUrl('images', 'thumb'))
                    <tr>
                        <td>
                            <div class="fi-order-items-product">
                                @if ($image)
                                    <img src="{{ $image }}" alt="" class="fi-order-items-image">
                                @endif
                                <span class="fi-order-items-product-name">{{ $item->product_name_snapshot }}</span>
                            </div>
                        </td>
                        <td class="fi-order-items-sku">{{ $item->variant_sku_snapshot }}</td>
                        <td class="fi-order-items-number">{{ $item->quantity }}</td>
                        <td class="fi-order-items-number fi-order-items-price">৳ {{ number_format($item->unit_price / 100, 2) }}</td>
                        <td class="fi-order-items-number fi-order-items-total">৳ {{ number_format($item->line_total / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
