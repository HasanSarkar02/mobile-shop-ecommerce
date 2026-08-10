@extends('storefront.layout')

@section('title', 'Track Your Order - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-sm mx-auto px-4 py-16">
        <h1 class="text-2xl font-bold mb-6">Track Your Order</h1>

        @if ($errors->any())
            <p class="text-red-500 text-sm mb-4">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('storefront.track-order.show') }}" class="space-y-4">
            @csrf
            <input name="order_number" placeholder="Order number (e.g. ORD-2026-000123)"
                class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <input type="email" name="email" placeholder="Email used at checkout"
                class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <button class="w-full py-3 rounded-xl bg-[var(--brand)] text-white font-medium">Track Order</button>
        </form>
    </div>
@endsection
