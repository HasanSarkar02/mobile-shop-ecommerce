@php
    $movements = $getState();
@endphp

<style>
    .fi-order-inventory { color: #374151; font-size: 0.875rem; }
    .fi-order-inventory-table-wrap { border: 1px solid #e5e7eb; overflow-x: auto; width: 100%; }
    .fi-order-inventory-table { border-collapse: collapse; table-layout: fixed; min-width: 760px; width: 100%; }
    .fi-order-inventory-table th,
    .fi-order-inventory-table td { border-bottom: 1px solid #e5e7eb; padding: 0.75rem; text-align: left; vertical-align: top; }
    .fi-order-inventory-table th { background: #f9fafb; color: #6b7280; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
    .fi-order-inventory-table th:nth-child(1) { width: 145px; }
    .fi-order-inventory-table th:nth-child(2) { width: 125px; }
    .fi-order-inventory-table th:nth-child(3) { width: 180px; }
    .fi-order-inventory-table th:nth-child(4) { width: 105px; }
    .fi-order-inventory-table th:nth-child(5) { width: 95px; }
    .fi-order-inventory-table th:nth-child(6) { width: 190px; }
    .fi-order-inventory-date,
    .fi-order-inventory-number { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fi-order-inventory-number { text-align: right !important; }
    .fi-order-inventory-variant,
    .fi-order-inventory-details { overflow-wrap: anywhere; }
    .fi-order-inventory-subtext { color: #6b7280; display: block; font-size: 0.75rem; margin-top: 0.125rem; }
    .fi-order-inventory-type { background: #f3f4f6; border-radius: 0.375rem; color: #374151; display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.5rem; white-space: nowrap; }
    .fi-order-inventory-positive { color: #059669; font-weight: 600; }
    .fi-order-inventory-negative { color: #e11d48; font-weight: 600; }
    .fi-order-inventory-zero { color: #4b5563; font-weight: 600; }
    .fi-order-inventory-note { align-items: flex-start; background: #f9fafb; color: #6b7280; display: flex; font-size: 0.75rem; gap: 0.5rem; line-height: 1.4; margin-top: 0.75rem; padding: 0.625rem 0.75rem; }
    .fi-order-inventory-note svg { flex: 0 0 1rem; height: 1rem; margin-top: 0.125rem; width: 1rem; }
    .fi-order-inventory-empty { border: 1px dashed #d1d5db; color: #6b7280; font-size: 0.875rem; padding: 0.75rem 1rem; }
    .dark .fi-order-inventory { color: #d1d5db; }
    .dark .fi-order-inventory-table-wrap,
    .dark .fi-order-inventory-table th,
    .dark .fi-order-inventory-table td { border-color: rgba(255, 255, 255, 0.1); }
    .dark .fi-order-inventory-table th,
    .dark .fi-order-inventory-note { background: rgba(255, 255, 255, 0.05); }
    .dark .fi-order-inventory-table th,
    .dark .fi-order-inventory-subtext,
    .dark .fi-order-inventory-details,
    .dark .fi-order-inventory-note,
    .dark .fi-order-inventory-empty { color: #9ca3af; }
    .dark .fi-order-inventory-type { background: rgba(255, 255, 255, 0.1); color: #d1d5db; }
    @media (max-width: 720px) {
        .fi-order-inventory-table { min-width: 700px; }
        .fi-order-inventory-table th,
        .fi-order-inventory-table td { padding: 0.625rem 0.5rem; }
    }
</style>

<div class="fi-order-inventory">
    @if ($movements->isEmpty())
        <div class="fi-order-inventory-empty">No inventory movements recorded.</div>
    @else
        <div class="fi-order-inventory-table-wrap">
            <table class="fi-order-inventory-table">
                <colgroup>
                    <col style="width: 145px">
                    <col style="width: 125px">
                    <col style="width: 180px">
                    <col style="width: 105px">
                    <col style="width: 95px">
                    <col style="width: 190px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Variant</th>
                        <th class="fi-order-inventory-number">Qty Change</th>
                        <th class="fi-order-inventory-number">Qty After</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $movement)
                        @php
                            $changeClass = $movement->quantity_change > 0
                                ? 'fi-order-inventory-positive'
                                : ($movement->quantity_change < 0 ? 'fi-order-inventory-negative' : 'fi-order-inventory-zero');
                        @endphp
                        <tr>
                            <td class="fi-order-inventory-date">
                                <span>{{ $movement->created_at?->format('M j, Y') }}</span>
                                <span class="fi-order-inventory-subtext">{{ $movement->created_at?->format('H:i') }}</span>
                            </td>
                            <td><span class="fi-order-inventory-type">{{ $movement->type->label() }}</span></td>
                            <td class="fi-order-inventory-variant">{{ $movement->variant?->sku ?? '—' }}</td>
                            <td class="fi-order-inventory-number {{ $changeClass }}">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                            <td class="fi-order-inventory-number">{{ $movement->quantity_after }}</td>
                            <td class="fi-order-inventory-details">
                                @if ($movement->comment)
                                    {{ $movement->comment }}
                                @elseif ($movement->reason)
                                    Reason: {{ $movement->reason->label() }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="fi-order-inventory-note">
            <x-filament::icon icon="heroicon-o-information-circle" />
            <span>Movement-level inventory history for this order. Linked serial and IMEI details are shown with the relevant order item when available.</span>
        </div>
    @endif
</div>
