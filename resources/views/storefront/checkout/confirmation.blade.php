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
                            <span class="text-gray-600 dark:text-gray-300">{{ $item->product_name_snapshot }} ×
                                {{ $item->quantity }}</span>
                            <span class="font-medium">৳{{ number_format($item->line_total / 100) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-lg font-bold pt-3 mt-3 border-t border-gray-200 dark:border-gray-800">
                    <span>Total</span><span>৳{{ number_format($order->grand_total / 100) }}</span>
                </div>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            <x-ui.button variant="secondary" onclick="window.location='{{ route('storefront.track-order.form') }}'">Track
                Order</x-ui.button>
            <x-ui.button variant="primary" onclick="window.location='{{ route('storefront.home') }}'">Continue
                Shopping</x-ui.button>
        </div>
    </div>
@endsection
