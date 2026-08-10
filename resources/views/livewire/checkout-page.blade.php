<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Checkout</h1>

    @if ($issues)
        <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-900 rounded-lg p-4 mb-6">
            @foreach ($issues as $issue)
                <p class="text-sm text-amber-800 dark:text-amber-200">{{ $issue }}</p>
            @endforeach
        </div>
    @endif

    <form wire:submit="placeOrder" class="space-y-6">
        <div>
            <h2 class="font-semibold mb-3">Shipping Address</h2>
            @if ($customer)
                @foreach ($addresses as $address)
                    <label class="flex items-start gap-2 mb-2">
                        <input type="radio" wire:model="selectedAddressId" value="{{ $address->id }}">
                        <span class="text-sm">{{ $address->recipient_name }}, {{ $address->address_line_1 }},
                            {{ $address->city }}</span>
                    </label>
                @endforeach
            @else
                <div class="grid grid-cols-2 gap-3">
                    <input wire:model="guestName" placeholder="Full name"
                        class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestPhone" placeholder="Phone"
                        class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestEmail" placeholder="Email"
                        class="col-span-2 rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestAddress.recipient_name" placeholder="Recipient name"
                        class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestAddress.phone" placeholder="Delivery phone"
                        class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestAddress.address_line_1" placeholder="Address"
                        class="col-span-2 rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                    <input wire:model="guestAddress.city" placeholder="City"
                        class="rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
            @endif
        </div>

        <div>
            <h2 class="font-semibold mb-3">Shipping Method</h2>
            @foreach ($shippingMethods as $method)
                <label class="flex items-center gap-2 mb-2">
                    <input type="radio" wire:model="shippingMethodId" value="{{ $method->id }}">
                    <span class="text-sm">{{ $method->name }} — ৳{{ number_format($method->cost / 100) }}</span>
                </label>
            @endforeach
        </div>

        <div>
            <h2 class="font-semibold mb-3">Payment Method</h2>
            @foreach ($paymentMethods as $method)
                <label class="flex items-center gap-2 mb-2">
                    <input type="radio" wire:model="paymentMethodId" value="{{ $method->id }}">
                    <span class="text-sm">{{ $method->name }}</span>
                </label>
            @endforeach
        </div>

        <textarea wire:model="customerNote" placeholder="Order note (optional)"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" rows="2"></textarea>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-full" loading-target="placeOrder">Place
            Order</x-ui.button>
    </form>
</div>
