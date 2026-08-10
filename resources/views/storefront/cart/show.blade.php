@extends('storefront.layout')
@section('title', 'Your Cart - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])
    <livewire:cart-page />
@endsection
