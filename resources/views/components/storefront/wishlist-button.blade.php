@props(['id', 'wishlisted', 'variant' => 'card', 'class' => ''])

@php
    $isPdp = $variant === 'pdp';
    $defaultClass = $isPdp
        ? 'p-2.5 rounded-xl border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]'
        : 'absolute right-2 top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-600 shadow-sm ring-1 ring-black/5 transition duration-150 hover:text-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:scale-90 disabled:opacity-70 dark:bg-gray-900/90 dark:text-gray-300 dark:ring-white/10';
    $buttonClass = $class !== '' ? $class : $defaultClass;
@endphp

<button type="button" x-data="{}" x-init="$store.wishlist.seed({{ $id }}, {{ $wishlisted ? 'true' : 'false' }})"
    @click.prevent.stop="$store.wishlist.toggle({{ $id }})"
    :disabled="$store.wishlist.pending[{{ $id }}] || false"
    :aria-busy="$store.wishlist.pending[{{ $id }}] ? 'true' : 'false'"
    :aria-pressed="$store.wishlist.isWishlisted({{ $id }}) ? 'true' : 'false'"
    :aria-label="$store.wishlist.isWishlisted({{ $id }}) ? 'Remove from wishlist' : 'Add to wishlist'"
    class="{{ $buttonClass }}"
    @if($isPdp)
        :class="$store.wishlist.isWishlisted({{ $id }}) ? 'border-red-300 bg-red-50 dark:bg-red-950 text-red-600' : 'border-gray-300 dark:border-gray-700'"
    @endif
>
    <span x-show="!$store.wishlist.isWishlisted({{ $id }})">
        <x-ui.icon name="heart" class="{{ $isPdp ? 'w-5 h-5' : 'h-[18px] w-[18px]' }}" />
    </span>
    <span x-show="$store.wishlist.isWishlisted({{ $id }})" x-cloak>
        <x-ui.icon name="heart" :solid="true" class="{{ $isPdp ? 'w-5 h-5 text-red-600' : 'h-[18px] w-[18px] text-red-500' }}" />
    </span>
</button>
