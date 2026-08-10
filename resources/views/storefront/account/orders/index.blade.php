@extends('storefront.account.layout')

@section('title', 'My Orders - ' . tenant()->name)

@section('account-content')
    <h1 class="text-xl font-bold mb-4">My Orders</h1>
    @forelse($orders as $order)
        <a href="{{ route('storefront.account.orders.show', $order) }}"
            class="flex justify-between py-3 border-b border-gray-100 dark:border-gray-800">
            <span>{{ $order->order_number }}</span>
            <span>{{ $order->status->label() }}</span>
            <span>৳{{ number_format($order->grand_total / 100) }}</span>
        </a>
    @empty
        <p class="text-gray-500">No orders yet.</p>
    @endforelse
    {{ $orders->links() }}
@endsection
