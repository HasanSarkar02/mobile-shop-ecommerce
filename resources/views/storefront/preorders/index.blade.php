{{-- resources/views/storefront/preorders/index.blade.php --}}
@extends('storefront.layout')
@section('title', 'Pre-Orders - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'index,follow'])

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center gap-3 mb-6">
            <span class="px-3 py-1 rounded-full bg-purple-600 text-white text-xs font-bold">PRE-ORDER</span>
            <h1 class="text-2xl font-bold">Pre-Orders</h1>
        </div>
        <p class="text-sm text-gray-500 mb-6">Reserve upcoming products — pay now, ships around the estimated availability date.</p>

        @if ($products->isEmpty())
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-8 text-center text-sm text-gray-500">
                No pre-order products available right now.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($products as $product)
                    @include('storefront.partials.product-card', ['product' => $product])
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
@endsection