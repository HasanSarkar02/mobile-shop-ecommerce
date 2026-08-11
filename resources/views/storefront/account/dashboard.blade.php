@extends('storefront.account.layout')

@section('title', 'My Account - ' . tenant()->name)

@section('account-content')
    @php
        $customer = auth('customer')->user();
        $firstName = trim(\Illuminate\Support\Str::before($customer->name ?? '', ' ')) ?: ($customer->name ?? '');
        $hour = (int) now()->format('G');
        $greeting = $hour >= 5 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 17 ? 'Good afternoon' : 'Good evening');

        $icons = [
            'orders' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>',
            'wishlist' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>',
            'addresses' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>',
            'profile' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>',
        ];
    @endphp

    {{-- Greeting header --}}
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white p-6 sm:p-8 dark:border-gray-800 dark:from-gray-900 dark:to-gray-950">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $greeting }},</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">{{ $firstName }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>

        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
            <span class="h-2 w-2 rounded-full bg-green-500"></span>
            Signed in to {{ tenant()->name }}
        </p>
    </div>

    {{-- Quick actions --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('storefront.account.orders') }}"
            class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[var(--brand)] hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{!! $icons['orders'] !!}</span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900 dark:text-white">Orders</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Track &amp; view your orders</span>
            </span>
        </a>

        <a href="{{ route('storefront.wishlist') }}"
            class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[var(--brand)] hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{!! $icons['wishlist'] !!}</span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900 dark:text-white">Wishlist</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Products you saved</span>
            </span>
        </a>

        <a href="{{ route('storefront.account.addresses') }}"
            class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[var(--brand)] hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{!! $icons['addresses'] !!}</span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900 dark:text-white">Addresses</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Manage saved addresses</span>
            </span>
        </a>

        <a href="{{ route('storefront.account.profile') }}"
            class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-[var(--brand)] hover:shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{!! $icons['profile'] !!}</span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900 dark:text-white">Profile</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">Your personal details</span>
            </span>
        </a>
    </div>

    {{-- Account details --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your account</h2>
        <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->email }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Member since</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->created_at?->format('F j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Marketing</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $customer->marketing_opt_in ? 'Subscribed' : 'Opted out' }}</dd>
            </div>
        </dl>
    </div>
@endsection