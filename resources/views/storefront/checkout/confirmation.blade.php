{{-- resources/views/storefront/checkout/confirmation.blade.php --}}
@extends('storefront.layout')
@section('title', 'Order Confirmed - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-16 text-center">
        <span
            class="inline-flex w-16 h-16 rounded-full bg-green-100 dark:bg-green-950 text-green-600 items-center justify-center mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </span>
        <h1 class="text-2xl font-bold mb-2">Thank you!</h1>
        <p class="text-gray-500">Your order <strong class="text-gray-900 dark:text-gray-100">{{ $orderNumber }}</strong>
            has been placed. We'll notify you as it progresses.</p>

        @if ($order)
            <div class="mt-8 rounded-2xl border border-gray-100 dark:border-gray-800 p-5 text-left">
                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-300">
                                {{ $item->product_name_snapshot }} × {{ $item->quantity }}
                                @if (($item->fulfillment_strategy ?? null) === 'preorder')
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">PRE-ORDER</span>
                                @endif
                            </span>
                            <span class="font-medium">৳{{ number_format($item->line_total / 100) }}</span>
                        </div>
                        @if (($item->fulfillment_strategy ?? null) === 'preorder' && $item->expected_available_at)
                            <p class="text-xs text-purple-600 dark:text-purple-400 -mt-2">Expected availability {{ $item->expected_available_at->format('M j, Y') }} — estimate</p>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-between text-lg font-bold pt-3 mt-3 border-t border-gray-200 dark:border-gray-800">
                    <span>Total</span><span>৳{{ number_format($order->grand_total / 100) }}</span>
                </div>
                @if ($order->paymentMethod)
                    <p class="mt-3 text-sm text-gray-500">Payment method: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $order->paymentMethod->displayName() }}</span></p>
                @endif
                @php
                    $hasPreorderItems = $order->items->contains(fn($it) => ($it->fulfillment_strategy ?? null) === 'preorder');
                @endphp
                @if ($hasPreorderItems)
                    <div class="mt-3 rounded-lg bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-900 p-3 text-xs text-purple-700 dark:text-purple-300">
                        Pre-order items ship separately around their expected availability date. In-stock items ship now.
                    </div>
                @endif
            </div>

            @php
                $pm = $order->paymentMethod;
                $needsManual = $pm && $pm->requires_verification && in_array($pm->type->value, ['manual_mfs','bank_transfer'], true);
            @endphp
            @if ($needsManual)
                <livewire:manual-payment-submission :orderNumber="$orderNumber" :key="$orderNumber" />
            @elseif($pm && $pm->type->value === 'cod')
                <div class="mt-6 rounded-2xl border border-gray-100 dark:border-gray-800 p-5 text-left bg-gray-50 dark:bg-gray-900/40">
                    <h3 class="font-semibold flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Cash on Delivery
                    </h3>
                    @if ($pm->instructions)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $pm->instructions }}</p>
                    @else
                        <p class="mt-2 text-sm text-gray-500">Your order will be delivered and you can pay in cash at that time.</p>
                    @endif
                </div>
            @endif
        @endif

        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            <x-ui.button variant="secondary" onclick="window.location='{{ route('storefront.track-order.form') }}'">Track
                Order</x-ui.button>
            <x-ui.button variant="primary" onclick="window.location='{{ route('storefront.home') }}'">Continue
                Shopping</x-ui.button>
        </div>
    </div>
@endsection
