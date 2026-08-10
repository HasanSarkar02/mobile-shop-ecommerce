@extends('storefront.layout')

@section('title', 'Order ' . $order->order_number . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-2xl mx-auto px-4 py-12">
        <h1 class="text-2xl font-bold mb-2">Order {{ $order->order_number }}</h1>
        <p class="text-gray-500 mb-6">Status: <span
                class="font-medium text-gray-900 dark:text-gray-100">{{ $order->status->label() }}</span></p>

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6">
            @foreach ($order->items as $item)
                <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-800">
                    <span>{{ $item->product_name_snapshot }} × {{ $item->quantity }}</span>
                    <span>৳{{ number_format($item->line_total / 100) }}</span>
                </div>
            @endforeach
            <p class="text-right font-bold mt-4">Total: ৳{{ number_format($order->grand_total / 100) }}</p>
        </div>

        @if ($fulfillment = $order->fulfillments->first())
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6 text-sm">
                <p><span class="text-gray-500">Fulfillment status:</span> {{ $fulfillment->status->label() }}</p>
                @if ($fulfillment->courier_name)
                    <p><span class="text-gray-500">Courier:</span> {{ $fulfillment->courier_name }}</p>
                @endif
                @if ($fulfillment->tracking_number)
                    <p><span class="text-gray-500">Tracking number:</span> {{ $fulfillment->tracking_number }}</p>
                @endif
            </div>
        @endif

        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
            <h2 class="font-semibold mb-3">Timeline</h2>
            @foreach ($order->events as $event)
                <div class="flex justify-between text-sm py-1">
                    <span>{{ $event->description }}</span>
                    <span class="text-gray-400">{{ $event->created_at->diffForHumans() }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endsection
