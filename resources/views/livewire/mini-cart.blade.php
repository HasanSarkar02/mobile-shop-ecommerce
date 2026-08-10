<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" class="relative p-2">
        🛒
        @if ($itemCount > 0)
            <span
                class="absolute -top-1 -right-1 bg-[var(--brand)] text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ $itemCount }}</span>
        @endif
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak
        class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-lg p-4 z-50">
        @forelse($items as $item)
            <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-800">
                <span>{{ $item->variant->product->name ?? $item->variant->sku }} × {{ $item->quantity }}</span>
                <span>৳{{ number_format($item->lineTotal() / 100) }}</span>
            </div>
        @empty
            <x-ui.empty-state title="Your cart is empty" />
        @endforelse

        @if ($itemCount > 0)
            <div class="flex justify-between font-bold mt-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                <span>Subtotal</span>
                <span>৳{{ number_format($subtotal / 100) }}</span>
            </div>
            <x-ui.button variant="primary" size="md" class="w-full mt-3"
                onclick="window.location='{{ route('storefront.cart') }}'">View Cart</x-ui.button>
        @endif
    </div>
</div>
