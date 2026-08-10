@extends('storefront.layout')

@section('title', 'My Wishlist - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">My Wishlist</h1>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @forelse($wishlist->items as $item)
                @include('storefront.partials.product-card', ['product' => $item->product])
            @empty
                <p class="text-gray-500 col-span-full">Your wishlist is empty.</p>
            @endforelse
        </div>
    </div>
@endsection
