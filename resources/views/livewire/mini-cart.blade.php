<div x-data="{ open: false }" class="relative">
    @if ($variant === 'badge')
        <a href="{{ route('storefront.cart') }}"
            class="relative flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400"
            aria-label="Cart">
            <span class="relative">
                <x-ui.icon name="cart" class="w-6 h-6" />
                @if ($itemCount > 0)
                    <span
                        class="absolute -top-1 -right-1.5 bg-[var(--brand)] text-white text-[9px] font-semibold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-0.5">{{ $itemCount }}</span>
                @endif
            </span>
            <span>Cart</span>
        </a>
    @else
        <button @click="open = !open" @keydown.escape.window="open = false" :aria-expanded="open.toString()"
            class="relative p-2.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
            aria-label="Cart">
            <x-ui.icon name="cart" class="w-[22px] h-[22px]" />
            @if ($itemCount > 0)
                <span
                    class="absolute -top-0.5 -right-0.5 bg-[var(--brand)] text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $itemCount }}</span>
            @endif
        </button>

        <div x-show="open" @click.outside="open = false" x-cloak
            x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-elevated z-50">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <p class="text-sm font-semibold">Shopping Cart</p>
                <span class="text-xs text-gray-400" x-text="'{{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }}'"></span>
            </div>

            <div class="max-h-72 overflow-y-auto px-2 py-1">
                @forelse($items as $item)
                    <div class="flex items-center gap-3 px-2 py-2.5 border-b border-gray-50 dark:border-gray-800/60">
                        @php
                            $product = $item->variant?->product;
                            $thumb = $product ? $product->getFirstMediaUrl('images', 'thumb') : null;
                        @endphp
                        <div
                            class="w-11 h-11 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 flex-shrink-0">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $product->name }}" width="44" height="44"
                                    class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-800 dark:text-gray-100 truncate">{{ $product->name ?? $item->variant?->sku }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $item->quantity }} × ৳{{ number_format($item->unit_price / 100) }}
                            </p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex-shrink-0">
                            ৳{{ number_format($item->lineTotal() / 100) }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <p class="text-sm text-gray-400">Your cart is empty</p>
                        <a href="{{ route('storefront.home') }}"
                            class="inline-block mt-3 text-sm font-semibold text-[var(--brand)] hover:underline underline-offset-2">Start shopping</a>
                    </div>
                @endforelse
            </div>

            @if ($itemCount > 0)
                <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Subtotal</span>
                        <span class="text-base font-bold">৳{{ number_format($subtotal / 100) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ui.button variant="secondary" size="sm" class="w-full"
                            onclick="window.location='{{ route('storefront.cart') }}'">View Cart</x-ui.button>
                        <x-ui.button variant="primary" size="sm" class="w-full"
                            onclick="window.location='{{ route('storefront.checkout') }}'">Checkout</x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
