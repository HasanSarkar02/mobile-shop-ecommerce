@extends('storefront.account.layout')

@section('title', 'My Account - ' . tenant()->name)

@section('account-content')
    <h1 class="text-2xl font-bold mb-4">Welcome back, {{ auth('customer')->user()->name }}</h1>
    <p class="text-gray-500">Manage your orders, wishlist, and account details from here.</p>
@endsection
