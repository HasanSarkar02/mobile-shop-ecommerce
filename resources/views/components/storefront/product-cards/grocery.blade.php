@php
    $variant = $card['variant'] ?? null;
    $cta = $card['cta'] ?? null;
    $discount = $card['discount_percentage'] ?? null;
    $rating = $card['average_rating'];
    $reviews = $card['reviews_count'] ?? 0;
    $product = $card['product'] ?? null;
    $hasFreeDelivery = $card['has_free_delivery'] ?? false;
    $outOfStock = (bool) ($variant['is_out_of_stock'] ?? false);
    $locale = app()->getLocale();
    // format quantity with locale-aware digits
    $sellByUnitDisplay = '';
    $uomName = '';
    $perUnitLabel = '';
    $minLabel = '';
    if ($product?->sell_by_unit) {
        $rawQty = rtrim(rtrim(number_format((float)$product->sell_by_unit, 3, '.', ''), '0'), '.');
        // Bangla digits when locale bn
        if ($locale === 'bn') {
            $rawQty = strtr($rawQty, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
            $uomNameRaw = $product?->uom?->name ?? $product?->uom?->code ?? '';
            // keep UOM as is (already translated if needed)
            $uomName = $uomNameRaw;
        } else {
            $uomName = $product?->uom?->name ?? $product?->uom?->code ?? '';
        }
        $sellByUnitDisplay = $rawQty.' '.$uomName;
        $perUnitLabel = __('per').' '.$sellByUnitDisplay;
        $minLabel = __('minimum').' '.$sellByUnitDisplay;
    }
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
    @if ($discount)
        <div class="pointer-events-none absolute left-2 top-2 z-[2] rounded bg-red-500 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">-{{ $locale==='bn' ? strtr($discount, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']) : $discount }}{{ __('% Off') }}</div>
    @endif
    <x-storefront.wishlist-button :id="$card['id']" :wishlisted="$card['wishlisted']" />

    <a href="{{ $card['url'] }}" class="flex flex-col">
        <div class="relative isolate flex h-32 sm:h-40 w-full items-center justify-center overflow-hidden bg-white pt-2 dark:bg-white {{ $outOfStock ? 'opacity-60 grayscale' : '' }}">
            @if ($card['has_image'])
                <img src="{{ $card['image'] }}" alt="{{ $card['image_alt'] }}" loading="lazy" class="h-32 sm:h-40 w-full object-contain mix-blend-multiply dark:mix-blend-normal transition group-hover:scale-[1.02]">
            @else
                <span class="text-xs text-gray-400">{{ __('No image') }}</span>
            @endif
        </div>

        <div class="flex flex-1 flex-col p-2">
            <h3 class="line-clamp-2 min-h-[2.2rem] text-[13px] font-medium leading-tight text-gray-900 dark:text-gray-100">
                {{ $card['name'] }}
            </h3>

            <x-storefront.product-rating :rating="$rating" :count="$reviews" />

            <div class="mt-1 flex flex-wrap items-baseline gap-1 leading-none min-h-[18px]">
                @if ($variant)
                    <span class="text-[15px] font-bold leading-none text-red-600">{{ money_without_trailing_zeros((int) $variant['price']) }}</span>
                    @if ($variant['compare_at_price'] && $variant['compare_at_price'] > $variant['price'])
                        <span class="text-[10px] leading-none text-gray-400 line-through">{{ money_without_trailing_zeros((int) $variant['compare_at_price']) }}</span>
                    @endif
                @else
                    <span class="text-xs leading-none text-gray-400">{{ __('Price unavailable') }}</span>
                @endif
            </div>

            @if ($perUnitLabel)
                <p class="mt-1 text-xs leading-tight text-gray-600 dark:text-gray-400">
                    {{ $perUnitLabel }} @if($minLabel) <span class="text-gray-400">({{ $minLabel }})</span> @endif
                </p>
            @endif

            <div class="mt-1 h-[16px] flex items-center">
                @if ($hasFreeDelivery)
                    <p class="flex items-center gap-1 text-[11px] font-medium text-green-700 dark:text-green-400 leading-none">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                        {{ __('Free Delivery') }}
                    </p>
                @endif
            </div>
        </div>
    </a>

    @php
        $variantId = $cta['variant_id'] ?? $variant['id'] ?? null;
        $sellByUnitFloat = is_numeric($product?->sell_by_unit ?? null) ? (float) $product->sell_by_unit : 1.0;
        $sellByUnitFloat = $sellByUnitFloat > 0 ? $sellByUnitFloat : 1.0;
    @endphp
    <div class="mt-auto p-2 pt-0" x-data="{ variantId: {{ $variantId !== null ? $variantId : 'null' }}, quantity: {{ $sellByUnitFloat }} }" x-init="if(variantId && $store.cart) $store.cart.pending[variantId] = false">
        @if ($cta && $cta['type'] === 'add_to_cart')
            <button type="button" @click.prevent="console.log('Adding variant:', variantId, 'Qty:', quantity); if(!variantId){ console.error('Cart add failed: variantId is null', $el); $store.cart.toast('Variant not available', 'error'); return; } $store.cart.add(variantId, quantity, '{{ route('storefront.cart.store') }}')" :disabled="variantId && $store.cart.pending[variantId]"
                class="flex h-8 w-full cursor-pointer items-center justify-center gap-1 rounded-full bg-green-600 px-1 py-1.5 text-[11px] sm:text-xs font-semibold text-white whitespace-nowrap transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-green-600 disabled:cursor-wait">
                <svg class="hidden h-4 w-4 shrink-0 sm:inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span x-show="!variantId || !$store.cart.pending[variantId]" class="whitespace-nowrap">{{ __('Add to Cart') }}</span>
                <span x-show="variantId && $store.cart.pending[variantId]" x-cloak class="whitespace-nowrap">{{ __('Adding…') }}</span>
            </button>
        @elseif ($cta && $cta['type'] === 'select_options')
            <x-storefront.variant-modal :card="$card">
                <x-slot:trigger>
                    <button type="button" @click="open = true"
                        class="flex h-8 w-full cursor-pointer items-center justify-center gap-1 rounded-full bg-green-600 px-1 py-1.5 text-[11px] sm:text-xs font-semibold text-white whitespace-nowrap transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-green-600">
                        <svg class="hidden h-4 w-4 shrink-0 sm:inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="whitespace-nowrap">{{ __('Add to Cart') }}</span>
                    </button>
                </x-slot:trigger>
            </x-storefront.variant-modal>
        @elseif ($cta && $cta['type'] === 'disabled')
            <button type="button" disabled class="flex h-8 w-full cursor-not-allowed items-center justify-center rounded-full bg-gray-100 px-3 text-xs font-semibold text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                {{ $cta['label'] }}
            </button>
        @else
            <div class="h-8" aria-hidden="true"></div>
        @endif
    </div>
</div>
