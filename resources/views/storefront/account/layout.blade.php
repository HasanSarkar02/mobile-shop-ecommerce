@extends('storefront.layout')

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8 flex flex-col md:flex-row gap-8">
        <nav class="md:w-48 flex-shrink-0 space-y-2 text-sm">
            <a href="{{ route('storefront.account.dashboard') }}" class="block">Dashboard</a>
            <a href="{{ route('storefront.account.orders') }}" class="block">Orders</a>
            <a href="{{ route('storefront.wishlist') }}" class="block">Wishlist</a>
            <a href="{{ route('storefront.account.addresses') }}" class="block">Addresses</a>
            <a href="{{ route('storefront.account.profile') }}" class="block">Profile</a>
            <form method="POST" action="{{ route('storefront.logout') }}">@csrf<button
                    class="block text-red-500">Logout</button></form>
        </nav>
        <div class="flex-1">@yield('account-content')</div>
    </div>
@endsection
