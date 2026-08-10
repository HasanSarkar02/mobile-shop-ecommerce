<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Your Cart</h1>

    @forelse($items as $item)
        <div class="flex items-center gap-4 py-4 border-b border-gray-100 dark:border-gray-800"
            wire:key="cart-item-{{ $item->id }}">
            <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900 flex-shrink-0">
                @if ($url = $item->variant->getFirstMediaUrl('images', 'thumb'))
                    <img src="{{ $url }}" width="64" height="64" loading="lazy"
                        class="w-full h-full object-cover">
                @endif
            </div>
            <div class="flex-1">
                <p class="font-medium">{{ $item->variant->product->name ?? $item->variant->sku }}</p>
                <p class="text-sm text-gray-500">{{ $item->variant->sku }}</p>
            </div>
            <input type="number" min="0" value="{{ $item->quantity }}"
                wire:change="updateQuantity({{ $item->id }}, $event.target.value)" wire:loading.attr="disabled"
                wire:target="updateQuantity"
                class="w-16 rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-700 focus:border-[var(--brand)] focus:ring-[var(--brand)]">
            <span class="w-24 text-right font-medium">৳{{ number_format($item->lineTotal() / 100) }}</span>
            <x-ui.button variant="ghost" size="sm" wire:click="removeItem({{ $item->id }})" :loading-target="'removeItem'"
                class="text-red-500">Remove</x-ui.button>
        </div>
    @empty
        <x-ui.empty-state title="Your cart is empty" description="Browse our catalog to find something you'll love."
            actionLabel="Continue Shopping" :actionUrl="route('storefront.home')" />
    @endforelse

    @if ($items->isNotEmpty())
        <div class="mt-6">
            @if ($coupon)
                <div class="flex justify-between items-center p-3 rounded-lg bg-green-50 dark:bg-green-950 text-sm">
                    <span>Coupon <strong>{{ $coupon->code }}</strong> applied</span>
                    <button wire:click="removeCoupon" class="text-red-500">Remove</button>
                </div>
            @else
                <form wire:submit="applyCoupon" class="flex gap-2">
                    <x-ui.input name="couponCode" wire:model="couponCode" placeholder="Coupon code" :error="$couponError"
                        class="flex-1" />
                    <x-ui.button type="submit" variant="secondary" loading-target="applyCoupon">Apply</x-ui.button>
                </form>
            @endif
        </div>

        <div class="mt-6 space-y-1 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>৳{{ number_format($subtotal / 100) }}</span>
            </div>
            @if ($discount > 0)
                <div class="flex justify-between text-green-600">
                    <span>Discount</span><span>-৳{{ number_format($discount / 100) }}</span></div>
            @endif
            <div class="flex justify-between text-xl font-bold pt-2 border-t border-gray-200 dark:border-gray-800">
                <span>Total</span><span>৳{{ number_format(($subtotal - $discount) / 100) }}</span>
            </div>
        </div>
        <x-ui.button variant="primary" size="lg" class="w-full mt-4"
            onclick="window.location='{{ route('storefront.checkout') }}'">Proceed to Checkout</x-ui.button>
    @endif
</div>
