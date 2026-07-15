<x-filament-panels::page>
    @php($order = $this->record)

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                <h3 class="font-semibold mb-4">Items</h3>
                <table class="w-full text-sm">
                    @foreach ($order->items as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $item->product_name_snapshot }} <span
                                    class="text-gray-400">({{ $item->variant_sku_snapshot }})</span></td>
                            <td class="py-2 text-right">{{ $item->quantity }} ×
                                {{ number_format($item->unit_price / 100, 2) }}</td>
                            <td class="py-2 text-right font-medium">{{ number_format($item->line_total / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </table>
                <div class="mt-4 text-sm text-right space-y-1">
                    <p>Subtotal: {{ number_format($order->subtotal / 100, 2) }}</p>
                    <p>Shipping: {{ number_format($order->shipping_cost / 100, 2) }}</p>
                    <p>Tax: {{ number_format($order->tax_total / 100, 2) }}</p>
                    <p class="font-bold">Total: {{ $order->currency_code }}
                        {{ number_format($order->grand_total / 100, 2) }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                <h3 class="font-semibold mb-4">Timeline</h3>
                <ul class="space-y-3 text-sm">
                    @foreach ($order->events as $event)
                        <li class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-2">
                            <span>{{ $event->description }}</span>
                            <span class="text-gray-400">{{ $event->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="space-y-6">
            <div
                class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 text-sm space-y-2">
                <p><span class="text-gray-500">Order #</span> {{ $order->order_number }}</p>
                <p><span class="text-gray-500">Invoice #</span> {{ $order->invoice_number }}</p>
                <p><span class="text-gray-500">Customer</span> {{ $order->customerDisplayName() }}</p>
                <p><span class="text-gray-500">Status</span> {{ $order->status->label() }}</p>
                <p><span class="text-gray-500">Source</span> {{ $order->order_source->label() }}</p>
                <p><span class="text-gray-500">Payment Method</span> {{ $order->paymentMethod?->name ?? '—' }}</p>
                <p><span class="text-gray-500">Shipping Method</span> {{ $order->shippingMethod?->name ?? '—' }}</p>
            </div>

            <div
                class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 text-sm space-y-2">
                <h3 class="font-semibold mb-2">Payments</h3>
                @forelse($order->payments as $payment)
                    <p>{{ number_format($payment->amount / 100, 2) }} — {{ $payment->status->label() }}</p>
                @empty
                    <p class="text-gray-400">No payments recorded.</p>
                @endforelse
            </div>

            @if ($order->internal_note)
                <div
                    class="bg-amber-50 dark:bg-amber-950 rounded-xl border border-amber-200 dark:border-amber-900 p-6 text-sm">
                    <h3 class="font-semibold mb-2">Internal Note</h3>
                    <p>{{ $order->internal_note }}</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
