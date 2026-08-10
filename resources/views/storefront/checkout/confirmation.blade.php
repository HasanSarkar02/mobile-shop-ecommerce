@extends('storefront.layout')
@section('title', 'Order Confirmed - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])
    <div class="max-w-xl mx-auto px-4 py-16 text-center">
        <h1 class="text-2xl font-bold mb-4">Thank you!</h1>
        <p class="text-gray-500">Your order <strong>{{ $orderNumber }}</strong> has been placed. We'll notify you as it
            progresses.</p>
        <a href="{{ route('storefront.home') }}" class="inline-block mt-6 text-[var(--brand)]">Continue shopping</a>
    </div>
@endsection
