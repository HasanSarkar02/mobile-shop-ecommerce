@extends('storefront.layout')

@section('content')
    @php
        $icons = [
            'home' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.15-.439 1.59 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>',
            'orders' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>',
            'wishlist' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>',
            'addresses' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>',
            'profile' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>',
            'logout' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>',
        ];

        $navItems = [
            ['label' => 'Dashboard', 'route' => 'storefront.account.dashboard', 'pattern' => 'storefront.account.dashboard', 'icon' => 'home'],
            ['label' => 'Orders', 'route' => 'storefront.account.orders', 'pattern' => 'storefront.account.orders*', 'icon' => 'orders'],
            ['label' => 'Wishlist', 'route' => 'storefront.wishlist', 'pattern' => 'storefront.wishlist', 'icon' => 'wishlist'],
            ['label' => 'Addresses', 'route' => 'storefront.account.addresses', 'pattern' => 'storefront.account.addresses*', 'icon' => 'addresses'],
            ['label' => 'Profile', 'route' => 'storefront.account.profile', 'pattern' => 'storefront.account.profile*', 'icon' => 'profile'],
        ];
    @endphp

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:flex lg:gap-10">
        {{-- Mobile nav (scrollable tabs) --}}
        <nav class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 lg:hidden" aria-label="Account navigation">
            @foreach ($navItems as $item)
                @php($active = request()->routeIs($item['pattern']))
                <a href="{{ route($item['route']) }}"
                    class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border px-4 py-2 text-sm font-medium transition {{ $active ? 'border-transparent bg-[var(--brand)] text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
            <form method="POST" action="{{ route('storefront.logout') }}">
                @csrf
                <button type="submit"
                    class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:border-red-300 dark:border-gray-800 dark:bg-gray-900 dark:text-red-400">
                    Logout
                </button>
            </form>
        </nav>

        {{-- Desktop sidebar --}}
        <nav class="hidden w-56 shrink-0 space-y-1 lg:block" aria-label="Account navigation">
            @foreach ($navItems as $item)
                @php($active = request()->routeIs($item['pattern']))
                <a href="{{ route($item['route']) }}"
                    class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition {{ $active ? 'bg-[var(--brand)] text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-900' }}">
                    <span class="shrink-0">{!! $icons[$item['icon']] !!}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach

            <form method="POST" action="{{ route('storefront.logout') }}" class="pt-2">
                @csrf
                <button type="submit"
                    class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                    <span class="shrink-0">{!! $icons['logout'] !!}</span>
                    Logout
                </button>
            </form>
        </nav>

        <div class="min-w-0 flex-1">
            @yield('account-content')
        </div>
    </div>
@endsection