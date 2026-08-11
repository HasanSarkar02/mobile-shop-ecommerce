<div class="overflow-x-auto">
    <table class="w-full min-w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <th class="py-2 pr-3 font-medium">Product</th>
                <th class="py-2 pr-3 font-medium">SKU</th>
                <th class="py-2 pr-3 text-right font-medium">Qty</th>
                <th class="py-2 pr-3 text-right font-medium">Unit Price</th>
                <th class="py-2 text-right font-medium">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($getState() as $item)
                <tr>
                    <td class="py-3 pr-3 align-top">
                        <div class="flex items-start gap-3">
                            @php($image = $item->variant?->getFirstMediaUrl('images', 'thumb'))
                            @if ($image)
                                <img
                                    src="{{ $image }}"
                                    alt=""
                                    class="h-12 w-12 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-white/10"
                                >
                            @endif
                            <span class="min-w-0 break-words font-medium text-gray-950 dark:text-white">
                                {{ $item->product_name_snapshot }}
                            </span>
                        </div>
                    </td>
                    <td class="py-3 pr-3 align-top text-gray-500 dark:text-gray-400">
                        {{ $item->variant_sku_snapshot }}
                    </td>
                    <td class="py-3 pr-3 text-right align-top tabular-nums text-gray-700 dark:text-gray-300">
                        {{ $item->quantity }}
                    </td>
                    <td class="py-3 pr-3 text-right align-top tabular-nums text-gray-700 dark:text-gray-300">
                        ৳ {{ number_format($item->unit_price / 100, 2) }}
                    </td>
                    <td class="py-3 text-right align-top font-semibold tabular-nums text-gray-950 dark:text-white">
                        ৳ {{ number_format($item->line_total / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>