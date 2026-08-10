@extends('storefront.account.layout')

@section('title', $order->order_number . ' - ' . tenant()->name)

@section('account-content')
    <h1 class="text-xl font-bold mb-4">Order {{ $order->order_number }}</h1>
    <p class="mb-4">Status: {{ $order->status->label() }}</p>
    @foreach ($order->items as $item)
        <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-800">
            <span>{{ $item->product_name_snapshot }} × {{ $item->quantity }}</span>
            <span>৳{{ number_format($item->line_total / 100) }}</span>
        </div>
    @endforeach
    <p class="font-bold text-right mt-4">Total: ৳{{ number_format($order->grand_total / 100) }}</p>
@endsection
