{{-- resources/views/livewire/cart-page.blade.php --}}
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold tracking-tight mb-6">Your Cart</h1>

    @if ($items->isEmpty())
        <x-ui.empty-state title="Your cart is empty" description="Browse our catalog to find something you'll love."
            actionLabel="Continue Shopping" :actionUrl="route('storefront.home')" />
    @else
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 min-w-0 divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($items as $item)
                    <div class="flex items-center gap-4 py-4" wire:key="cart-item-{{ $item->id }}"
                        wire:loading.class="opacity-50"
                        wire:target="updateQuantity({{ $item->id }}),removeItem({{ $item->id }})">
                        <div class="w-20 h-20 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-900 flex-shrink-0">
                            @if ($url = $item->variant->getFirstMediaUrl('images', 'thumb'))
                                <img src="{{ $url }}" width="80" height="80" loading="lazy"
                                    class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ $item->variant->product->name ?? $item->variant->sku }}
                            </p>
                            <p class="text-sm text-gray-500">{{ $item->variant->sku }}</p>
                            <button wire:click="removeItem({{ $item->id }})" wire:loading.attr="disabled"
                                wire:target="removeItem({{ $item->id }})"
                                class="text-xs text-red-500 hover:underline mt-1">Remove</button>
                        </div>
                        <div
                            class="flex items-center border border-gray-200 dark:border-gray-800 rounded-xl flex-shrink-0">
                            <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                @disabled($item->quantity <= 1)
                                class="w-8 h-9 flex items-center justify-center text-base disabled:opacity-30"
                                aria-label="Decrease quantity">−</button>
                            <span class="w-8 text-center text-sm">{{ $item->quantity }}</span>
                            <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                class="w-8 h-9 flex items-center justify-center text-base"
                                aria-label="Increase quantity">+</button>
                        </div>
                        <span
                            class="w-24 text-right font-semibold flex-shrink-0">৳{{ number_format($item->lineTotal() / 100) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="w-full lg:w-80 flex-shrink-0 lg:sticky lg:top-24">
                <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                    @if ($coupon)
                        <div
                            class="flex justify-between items-center p-3 rounded-xl bg-green-50 dark:bg-green-950 text-sm mb-4">
                            <span>Coupon <strong>{{ $coupon->code }}</strong> applied</span>
                            <button wire:click="removeCoupon" class="text-red-500 hover:underline">Remove</button>
                        </div>
                    @else
                        <form wire:submit="applyCoupon" class="flex gap-2 mb-4">
                            <x-ui.input name="couponCode" wire:model="couponCode" placeholder="Coupon code"
                                :error="$couponError" class="flex-1" />
                            <x-ui.button type="submit" variant="secondary"
                                loading-target="applyCoupon">Apply</x-ui.button>
                        </form>
                    @endif

                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><span
                                class="text-gray-500">Subtotal</span><span>৳{{ number_format($subtotal / 100) }}</span>
                        </div>
                        @if ($discount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Discount</span><span>-৳{{ number_format($discount / 100) }}</span></div>
                        @endif
                        <div
                            class="flex justify-between text-xl font-bold pt-3 mt-1 border-t border-gray-200 dark:border-gray-800">
                            <span>Total</span><span>৳{{ number_format(($subtotal - $discount) / 100) }}</span>
                        </div>
                        <p class="text-xs text-gray-400">Shipping calculated at checkout.</p>
                    </div>

                    <x-ui.button variant="primary" size="lg" class="w-full mt-4"
                        onclick="window.location='{{ route('storefront.checkout') }}'">
                        Proceed to Checkout
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
