@extends('storefront.layout')

@section('title', 'My Wishlist - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">My Wishlist</h1>

        @if ($cards->isNotEmpty())
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach ($cards as $card)
                    @include('storefront.partials.product-card', ['card' => $card])
                @endforeach
            </div>
        @else
            <x-ui.empty-state title="Your wishlist is empty"
                description="Tap the heart on any product to save it here for later."
                actionLabel="Start shopping" actionUrl="{{ route('storefront.home') }}" />
        @endif
    </div>
@endsection