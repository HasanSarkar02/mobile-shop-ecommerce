<div class="space-y-6">
    {{-- Price range --}}
    <div>
        <p class="text-sm font-semibold mb-2.5">Price Range (৳)</p>
        <div class="flex items-center gap-2">
            <input type="number" wire:model.live.debounce.600ms="priceMin" placeholder="Min" min="0"
                class="w-full rounded-lg border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-[var(--brand)] focus:ring-[var(--brand)] transition">
            <span class="text-gray-400">&ndash;</span>
            <input type="number" wire:model.live.debounce.600ms="priceMax" placeholder="Max" min="0"
                class="w-full rounded-lg border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-sm focus:border-[var(--brand)] focus:ring-[var(--brand)] transition">
        </div>
        @if ($facets['price_range']?->min_price !== null)
            <p class="text-xs text-gray-400 mt-1.5">
                ৳{{ number_format($facets['price_range']->min_price / 100) }} &ndash;
                ৳{{ number_format($facets['price_range']->max_price / 100) }} available
            </p>
        @endif
    </div>

    {{-- Availability / status flags --}}
    <div class="space-y-1 pt-1 border-t border-gray-100 dark:border-gray-800">
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="inStockOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            In Stock
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="onSaleOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            On Sale
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="newArrivalOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            New Arrival
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="emiOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            EMI Available
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="warrantyOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            Warranty Included
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none py-1.5">
            <input type="checkbox" wire:model.live="officialOnly"
                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
            Official Product
        </label>
    </div>

    {{-- Brands --}}
    @if ($facets['brands']->isNotEmpty())
        <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
            <p class="text-sm font-semibold mb-2.5">Brand</p>
            <div class="space-y-1 max-h-48 overflow-y-auto pr-1">
                @foreach ($facets['brands'] as $brand)
                    <label class="flex items-center justify-between gap-2 text-sm cursor-pointer select-none py-1.5">
                        <span class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="brandIds" value="{{ $brand->id }}"
                                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
                            {{ $brand->name }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $brand->products_count }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dynamic, metadata-driven attribute facets — no attribute code is ever hardcoded here --}}
    @foreach ($facets['attributes'] as $code => $facet)
        <div class="pt-1 border-t border-gray-100 dark:border-gray-800" x-data="{ expanded: true }">
            <button type="button" @click="expanded = !expanded"
                class="w-full flex items-center justify-between text-sm font-semibold mb-2.5">
                {{ $facet['label'] }}
                <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-60 transition"
                    x-bind:class="expanded ? '' : '-rotate-90'" />
            </button>
            <div x-show="expanded" x-collapse class="space-y-1 max-h-48 overflow-y-auto pr-1">
                @foreach ($facet['options'] as $option)
                    <label class="flex items-center justify-between gap-2 text-sm cursor-pointer select-none py-1.5">
                        <span class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="attr.{{ $code }}"
                                value="{{ $option['value'] }}"
                                class="rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)]">
                            {{ $option['value'] }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $option['count'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
