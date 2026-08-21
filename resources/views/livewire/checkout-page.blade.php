{{-- resources/views/livewire/checkout-page.blade.php --}}
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 lg:pb-8">
    <h1 class="text-2xl font-bold tracking-tight mb-6">Checkout</h1>

    @if ($issues)
        <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-900 rounded-2xl p-4 mb-6">
            @foreach ($issues as $issue)
                <p class="text-sm text-amber-800 dark:text-amber-200">{{ $issue }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-8 items-start">
        <form wire:submit="placeOrder" class="flex-1 min-w-0 space-y-6">
            {{-- Shipping Address --}}
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span
                        class="w-6 h-6 rounded-full bg-[var(--brand)] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                    <h2 class="font-semibold">Shipping Address</h2>
                </div>
                @if ($customer)
                    <div class="space-y-2">
                        @forelse ($addresses as $address)
                            <label @class([
                                'flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition',
                                'border-[var(--brand)] bg-[var(--brand)]/5' =>
                                    $selectedAddressId === $address->id,
                                'border-gray-200 dark:border-gray-800' =>
                                    $selectedAddressId !== $address->id,
                            ])>
                                <input type="radio" wire:model.live="selectedAddressId" value="{{ $address->id }}"
                                    class="mt-1 text-[var(--brand)] focus:ring-[var(--brand)]">
                                <span class="text-sm">
                                    <span class="font-medium block">{{ $address->recipient_name }}</span>
                                    {{ $address->address_line_1 }}, {{ $address->city }} · {{ $address->phone }}
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">No saved addresses yet. <a
                                    href="{{ route('storefront.account.addresses') }}"
                                    class="text-[var(--brand)] font-medium">Add one</a>.</p>
                        @endforelse
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-ui.input name="guestName" wire:model="guestName" label="Full name" />
                        <x-ui.input name="guestPhone" wire:model="guestPhone" label="Phone" />
                        <x-ui.input name="guestEmail" wire:model="guestEmail" label="Email" class="sm:col-span-2" />
                        <x-ui.input name="guestAddress.recipient_name" wire:model="guestAddress.recipient_name"
                            label="Recipient name" />
                        <x-ui.input name="guestAddress.phone" wire:model="guestAddress.phone" label="Delivery phone" />
                        <x-ui.input name="guestAddress.address_line_1" wire:model="guestAddress.address_line_1"
                            label="Address" class="sm:col-span-2" />
                        <x-ui.input name="guestAddress.city" wire:model="guestAddress.city" label="City" />
                    </div>
                    <p class="text-sm text-gray-500 mt-3">Already have an account? <a
                            href="{{ route('storefront.login') }}" class="text-[var(--brand)] font-medium">Log in</a>
                    </p>
                @endif
            </div>

            {{-- Shipping Method --}}
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span
                        class="w-6 h-6 rounded-full bg-[var(--brand)] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                    <h2 class="font-semibold">Shipping Method</h2>
                </div>
                <div class="space-y-2">
                    @foreach ($shippingMethods as $method)
                        <label @class([
                            'flex items-center justify-between gap-3 p-3 rounded-xl border cursor-pointer transition',
                            'border-[var(--brand)] bg-[var(--brand)]/5' =>
                                $shippingMethodId === $method->id,
                            'border-gray-200 dark:border-gray-800' => $shippingMethodId !== $method->id,
                        ])>
                            <span class="flex items-center gap-3">
                                <input type="radio" wire:model.live="shippingMethodId" value="{{ $method->id }}"
                                    class="text-[var(--brand)] focus:ring-[var(--brand)]">
                                <span class="text-sm font-medium">{{ $method->name }}</span>
                            </span>
                            <span class="text-sm text-gray-500">৳{{ number_format($method->cost / 100) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Payment Method --}}
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span
                        class="w-6 h-6 rounded-full bg-[var(--brand)] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                    <h2 class="font-semibold">Payment Method</h2>
                </div>
                <div class="space-y-2">
                    @foreach ($paymentMethods as $method)
                        @php
                            $isManualMfs = $method->type === \App\Enums\PaymentMethodType::ManualMfs;
                            $isBank = $method->type === \App\Enums\PaymentMethodType::BankTransfer;
                            $isCod = $method->type === \App\Enums\PaymentMethodType::Cod;
                            $isManual = $isManualMfs || $isBank;
                        @endphp
                        <label @class([
                            'flex flex-col gap-2 p-3 rounded-xl border cursor-pointer transition',
                            'border-[var(--brand)] bg-[var(--brand)]/5' =>
                                $paymentMethodId === $method->id,
                            'border-gray-200 dark:border-gray-800' => $paymentMethodId !== $method->id,
                        ])>
                            <span class="flex items-center gap-3">
                                <input type="radio" wire:model.live="paymentMethodId" value="{{ $method->id }}"
                                    class="text-[var(--brand)] focus:ring-[var(--brand)]">
                                <span class="text-sm font-medium">{{ $method->displayName() }}</span>
                                @if ($isCod)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">COD</span>
                                @elseif($isManual)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Manual verification</span>
                                @endif
                            </span>
                            @if ($paymentMethodId === $method->id)
                                @if ($isManual)
                                    <div class="ml-7 mt-1 rounded-lg bg-gray-50 dark:bg-gray-900 p-3 text-sm space-y-1">
                                        @if ($method->account_number)
                                            <p><span class="text-gray-500">Account:</span> <span class="font-mono font-medium">{{ $method->account_number }}</span> @if ($method->account_name) — {{ $method->account_name }} @endif</p>
                                        @endif
                                        @if ($isBank && $method->bank_name)
                                            <p><span class="text-gray-500">Bank:</span> {{ $method->bank_name }} @if ($method->branch_name) ({{ $method->branch_name }}) @endif</p>
                                        @endif
                                        @if ($method->instructions)
                                            <p class="text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $method->instructions }}</p>
                                        @endif
                                        <p class="text-xs text-amber-700 dark:text-amber-300">After paying, submit your Transaction ID on the order confirmation page for verification.</p>
                                    </div>
                                @elseif($isCod)
                                    @if ($method->instructions)
                                        <p class="ml-7 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $method->instructions }}</p>
                                    @else
                                        <p class="ml-7 text-sm text-gray-500">Pay with cash when your order is delivered.</p>
                                    @endif
                                @endif
                            @endif
                        </label>
                    @endforeach
                    @if ($paymentMethods->isEmpty())
                        <p class="text-sm text-gray-500">No payment methods are currently enabled for this store.</p>
                    @endif
                </div>
            </div>

            @if ($hasPreorder ?? false)
                <div class="rounded-2xl border border-purple-200 dark:border-purple-900 bg-purple-50 dark:bg-purple-950/40 p-4">
                    <h3 class="font-semibold text-purple-900 dark:text-purple-100 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span> Pre-order in your cart
                    </h3>
                    @if ($preorderEta)
                        <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">Earliest expected availability: <strong>{{ $preorderEta->format('M j, Y') }}</strong> — estimate, subject to change.</p>
                    @endif
                    @if ($isMixed ?? false)
                        <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">In-stock items will ship now. Pre-order items will ship separately around their ETA.</p>
                    @else
                        <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">This order contains pre-order items that will ship around the ETA.</p>
                    @endif
                    <label class="mt-3 flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="preorder_ack" class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span class="text-sm text-purple-900 dark:text-purple-100">I understand that pre-order items are paid now and ship around the estimated availability date.</span>
                    </label>
                    @error('preorder_ack') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                <label for="customerNote" class="font-semibold text-sm block mb-2">Order Note (optional)</label>
                <textarea id="customerNote" wire:model="customerNote"
                    class="w-full rounded-xl border-gray-200 dark:bg-gray-900 dark:border-gray-800 focus:border-[var(--brand)] focus:ring-[var(--brand)] transition"
                    rows="2"></textarea>
            </div>

            <x-ui.button type="submit" variant="primary" size="lg" class="w-full hidden lg:flex"
                loading-target="placeOrder">
                Place Order
            </x-ui.button>
        </form>

        {{-- Order summary --}}
        <div class="w-full lg:w-80 flex-shrink-0 lg:sticky lg:top-24">
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5">
                <h2 class="font-semibold mb-4">Order Summary</h2>
                @if ($isMixed ?? false)
                    <div class="mb-3 rounded-lg bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-900 p-2 text-xs text-purple-700 dark:text-purple-300">
                        Stock ships now · Pre-order ships around ETA
                    </div>
                @endif
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @foreach ($cartItems as $item)
                        @php $isPre = $item->variant?->fulfillment_strategy?->value === 'preorder'; @endphp
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-900 flex-shrink-0 relative">
                                @if ($url = $item->variant->getFirstMediaUrl('images', 'thumb'))
                                    <img src="{{ $url }}" width="48" height="48"
                                        class="w-full h-full object-cover">
                                @endif
                                <span
                                    class="absolute -top-1.5 -right-1.5 bg-gray-700 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">{{ $item->quantity }}</span>
                                @if ($isPre)
                                    <span class="absolute bottom-0 left-0 right-0 bg-purple-600 text-white text-[9px] font-bold text-center leading-3">PRE-ORDER</span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm truncate">{{ $item->variant->product->name ?? $item->variant->sku }}</p>
                                @if ($isPre && $item->variant?->expected_available_at)
                                    <p class="text-xs text-purple-600 dark:text-purple-400">ETA {{ $item->variant->expected_available_at->format('M j, Y') }}</p>
                                @endif
                            </div>
                            <p class="text-sm font-medium flex-shrink-0">৳{{ number_format($item->lineTotal() / 100) }}
                            </p>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-1.5 text-sm mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex justify-between"><span
                            class="text-gray-500">Subtotal</span><span>৳{{ number_format($subtotal / 100) }}</span>
                    </div>
                    @if ($discount > 0)
                        <div class="flex justify-between text-green-600">
                            <span>Discount</span><span>-৳{{ number_format($discount / 100) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between"><span
                            class="text-gray-500">Shipping</span><span>৳{{ number_format($shippingCost / 100) }}</span>
                    </div>
                    <div
                        class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200 dark:border-gray-800">
                        <span>Total</span><span>৳{{ number_format(($subtotal - $discount + $shippingCost) / 100) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sticky mobile place-order bar --}}
    <div
        class="lg:hidden fixed bottom-16 inset-x-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 px-4 py-3">
        <x-ui.button type="button" onclick="document.querySelector('form').requestSubmit()" variant="primary"
            size="lg" class="w-full" loading-target="placeOrder">
            Place Order — ৳{{ number_format(($subtotal - $discount + $shippingCost) / 100) }}
        </x-ui.button>
    </div>
</div>
