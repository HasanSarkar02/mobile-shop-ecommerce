@extends('storefront.layout')
@section('title', 'Checkout - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])
    <livewire:checkout-page />
@endsection
