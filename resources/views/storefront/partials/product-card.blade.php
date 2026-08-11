{{-- resources/views/storefront/partials/product-card.blade.php --}}
@php
    $slug = $product->translation('en')?->slug;
    $variant = $product->variants->first();
    $outOfStock = $variant && $variant->availability === \App\Enums\VariantAvailability::OutOfStock;
    $hasEmi = $product->relationLoaded('emiPlans') ? $product->emiPlans->isNotEmpty() : $product->emiPlans()->exists();
    $isWishlisted = isset($wishlistedProductIds) && $wishlistedProductIds->contains($product->id);
@endphp
{{-- Not a single wrapping <a> — the wishlist <button> below must not nest
     inside an <a> (invalid HTML content model). The <a> wraps only the
     clickable-to-PDP content; the wishlist button is a sibling positioned
     over the image. --}}
<div class="group relative">
    <a href="{{ route('storefront.product', $slug) }}" class="block">
        <div class="relative aspect-square rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-900">
            @if ($product->getFirstMediaUrl('images', 'thumb'))
                <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}" alt="{{ $product->name }}" width="400"
                    height="400" loading="lazy" decoding="async"
                    class="w-full h-full object-cover transition duration-300 {{ $outOfStock ? 'opacity-50 grayscale' : 'group-hover:scale-105' }}">
            @endif

            {{-- Top-left badge stack --}}
            <div class="absolute top-2.5 left-2.5 flex flex-col gap-1.5 items-start pointer-events-none">
                @if ($variant && $variant->compare_at_price && $variant->compare_at_price > $variant->price)
                    <span
                        class="text-[11px] font-semibold leading-none bg-red-600 text-white px-2 py-1 rounded-full shadow-soft">
                        {{ (int) round((($variant->compare_at_price - $variant->price) / $variant->compare_at_price) * 100) }}%
                        OFF
                    </span>
                @endif
                @if ($product->is_official_import)
                    <span
                        class="text-[11px] font-semibold leading-none bg-white/95 dark:bg-gray-900/95 text-gray-700 dark:text-gray-200 px-2 py-1 rounded-full shadow-soft">
                        Official
                    </span>
                @endif
                @if ($variant?->isPreOrder())
                    <span
                        class="text-[11px] font-semibold leading-none bg-amber-500 text-white px-2 py-1 rounded-full shadow-soft">
                        Pre-Order
                    </span>
                @endif
            </div>

            @if ($outOfStock)
                <div class="absolute inset-0 flex items-center justify-center bg-black/10 pointer-events-none">
                    <span class="text-xs font-semibold bg-gray-900/90 text-white px-3 py-1.5 rounded-full">Out of
                        Stock</span>
                </div>
            @endif
        </div>

        <p
            class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2 group-hover:text-[var(--brand)] transition">
            {{ $product->name }}</p>

        @if ($product->reviews_count > 0)
            <div class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <span class="text-amber-500">★</span>
                <span
                    class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($product->average_rating, 1) }}</span>
                <span>({{ $product->reviews_count }})</span>
            </div>
        @endif

        @if ($variant)
            <div class="mt-1.5">
                <x-ui.price size="sm" :price="$variant->price" :compareAtPrice="$variant->compare_at_price" />
            </div>
            @if ($hasEmi)
                <p class="mt-0.5 text-xs text-[var(--brand)] font-medium">EMI available</p>
            @endif
        @endif
    </a>

    <button type="button" x-data="{
        wishlisted: {{ $isWishlisted ? 'true' : 'false' }},
        pending: false,
        toggle() {
            if (this.pending) return;
            this.pending = true;
            this.wishlisted = !this.wishlisted;
            fetch('{{ route('storefront.wishlist.toggle') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: {{ $product->id }} }),
            }).catch(() => { this.wishlisted = !this.wishlisted; }).finally(() => { this.pending = false; });
        },
    }" @click="toggle()"
        class="absolute top-2.5 right-2.5 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft hover:bg-white dark:hover:bg-gray-900 transition"
        :aria-pressed="wishlisted" aria-label="Toggle wishlist">
        <span x-show="!wishlisted"><x-ui.icon name="heart" class="w-4 h-4 text-gray-600 dark:text-gray-300" /></span>
        <span x-show="wishlisted" x-cloak><x-ui.icon name="heart" :solid="true"
                class="w-4 h-4 text-red-500" /></span>
    </button>
</div>
