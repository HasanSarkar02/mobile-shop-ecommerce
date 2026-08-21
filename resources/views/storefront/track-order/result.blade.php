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
                    <span>
                        {{ $item->product_name_snapshot }} × {{ $item->quantity }}
                        @if (($item->fulfillment_strategy ?? null) === 'preorder')
                            <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-xs">PRE-ORDER</span>
                        @endif
                    </span>
                    <span>৳{{ number_format($item->line_total / 100) }}</span>
                </div>
                @if (($item->fulfillment_strategy ?? null) === 'preorder' && $item->expected_available_at)
                    <p class="text-xs text-purple-600 -mt-1 mb-2">Expected availability {{ $item->expected_available_at->format('M j, Y') }}</p>
                @endif
            @endforeach
            <p class="text-right font-bold mt-4">Total: ৳{{ number_format($order->grand_total / 100) }}</p>
        </div>

        @if ($order->fulfillments->isNotEmpty())
            @foreach ($order->fulfillments as $fulfillment)
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6 text-sm">
                    <p><span class="text-gray-500">Fulfillment ({{ ucfirst($fulfillment->fulfillment_group ?? 'stock') }}):</span> {{ $fulfillment->status->label() }}</p>
                    @if ($fulfillment->expected_available_at)
                        <p><span class="text-gray-500">ETA:</span> {{ $fulfillment->expected_available_at->format('M j, Y') }}</p>
                    @endif
                    @if ($fulfillment->courier_name)
                        <p><span class="text-gray-500">Courier:</span> {{ $fulfillment->courier_name }}</p>
                    @endif
                    @if ($fulfillment->tracking_number)
                        <p><span class="text-gray-500">Tracking number:</span> {{ $fulfillment->tracking_number }}</p>
                    @endif
                </div>
            @endforeach
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
