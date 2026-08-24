@props(['card'])

{{-- F.7 variant-selection modal: reuses the single shared engine
     `window.variantSelectionState` (defined in resources/js/app.js) and the
     shared `x-storefront.variant-selector` primitive — no second engine.
     Additive: only rendered for products where `requires_selection` is true;
     otherwise the card keeps its direct Add to Cart. --}}
@php
    $variants = $card['modal_variants'] ?? [];
    $dimensions = $card['modal_dimensions'] ?? [];
    $productName = $card['name'] ?? '';
    $productUrl = $card['url'] ?? '#';
@endphp
<div x-data="{
        open: false,
        ...variantSelectionState(@js($variants), @js($dimensions), true, null),
        cartLoading: false,
        close() {
            this.open = false;
            this.selected = {};
            this.currentVariantId = null;
            this.unavailable = false;
        },
        addToCart() {
            const v = this.current();
            if (!v || !v.purchasable) return;
            this.cartLoading = true;
            this.$store.cart.add(v.id).then(() => {
                // Close only after the store reports success (pending cleared)
                this.close();
            }).finally(() => {
                this.cartLoading = false;
            });
        }
    }"
    class="contents"
>
    {{-- Trigger: visually identical to the former "Select Options" link, but
         opens the modal when JS is available. No-JS fallback is the link to PDP
         inside the modal footer. --}}
    <button type="button" @click="open = true"
        class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 px-3 text-xs font-semibold text-gray-800 transition hover:border-[var(--brand)] hover:text-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--brand)] dark:border-gray-700 dark:text-gray-100 dark:focus-visible:ring-offset-gray-900 sm:text-sm">
        Select Options
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            role="dialog" aria-modal="true" aria-label="Select options for {{ $productName }}"
            @keydown.escape.window="close()"
            @click="close()"
        >
            <div class="absolute inset-0 bg-black/60" aria-hidden="true"></div>

            <div class="relative w-full max-w-lg max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden"
                @click.stop
            >
                <header class="flex items-start justify-between gap-4 border-b border-gray-200 dark:border-gray-800 p-5">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold leading-tight truncate">{{ $productName }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose your options</p>
                    </div>
                    <button type="button" @click="close()" aria-label="Close"
                        class="p-2 rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                        <x-ui.icon name="close" class="w-5 h-5" />
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    {{-- Shared selector primitive — same engine the PDP uses --}}
                    <x-storefront.variant-selector />

                    <template x-if="selectionIssueType()">
                        <div class="rounded-xl border p-3 text-sm"
                            :class="selectionIssueType() === 'invalid' ?
                                'border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300' :
                                'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300'">
                            <span x-text="selectionMessage()"></span>
                        </div>
                    </template>

                    <template x-if="current()">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="font-bold text-xl" x-text="formatPrice(current().price)"></span>
                            <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                                <span class="text-gray-400 line-through text-sm" x-text="formatPrice(current().compare_at_price)"></span>
                            </template>
                        </div>
                    </template>
                </div>

                <footer class="border-t border-gray-200 dark:border-gray-800 p-5 flex flex-col gap-3">
                    <x-ui.button variant="primary" size="lg" class="w-full"
                        @click="addToCart()"
                        x-bind:disabled="cartLoading || !current() || !current().purchasable">
                        <span x-show="!cartLoading" x-text="ctaLabel()"></span>
                        <span x-show="cartLoading" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Adding…
                        </span>
                    </x-ui.button>

                    <a href="{{ $productUrl }}" class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-[var(--brand)]">
                        View full details →
                    </a>
                </footer>
            </div>
        </div>
    </template>
</div>
