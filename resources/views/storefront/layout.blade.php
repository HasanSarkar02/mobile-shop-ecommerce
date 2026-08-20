<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--brand: {{ $theme?->primary_color ?? '#16a34a' }}">

<head>
    {{-- Runs before CSS paints to avoid a flash of the wrong theme; must stay
         inline and above @vite (localStorage/matchMedia aren't available at
         build time, and this needs to execute before first paint). --}}
    <script>
        if (localStorage.storefrontTheme === 'dark' || (!('storefrontTheme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', tenant()->name)</title>
    @if ($theme?->favicon_path)
        <link rel="icon" href="{{ asset('storage/' . $theme->favicon_path) }}">
    @endif
    @stack('meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

    <body class="bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 antialiased pb-16 lg:pb-0"
        data-wishlist-toggle="{{ route('storefront.wishlist.toggle') }}"
        data-cart-store="{{ route('storefront.cart.store') }}">
        @if (session()->has('support_mode'))
            @php $isWrite = session('support_mode.is_write_enabled', false); @endphp
            <div style="background-color:{{ $isWrite ? '#dc2626' : '#d97706' }};color:#fff;padding:0.625rem 1rem;display:flex;align-items:center;justify-content:center;gap:0.75rem;font-size:0.875rem;font-weight:600;">
                <span>{{ $isWrite ? '⚠️ READ/WRITE Support Mode Active - Proceed with Caution' : 'Read-Only Support Mode' }} — {{ tenant()?->name }}</span>
                <form method="POST" action="{{ route('support.exit') }}" style="margin:0;">
                    @csrf
                    <button type="submit" style="background:#fff;color:#dc2626;border:0;border-radius:0.375rem;padding:0.25rem 0.75rem;font-weight:700;cursor:pointer;">Exit Support Mode</button>
                </form>
            </div>
        @endif
        <x-storefront.announcement-bar :announcement="$announcement" />

    <header
        class="sticky top-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-b border-gray-200 dark:border-gray-800">
        <x-storefront.desktop-header :header-menu="$headerMenu" :header-categories="$headerCategories" :theme="$theme" :wishlist-count="$wishlistCount" />
        <x-storefront.mobile-header :header-menu="$headerMenu" :theme="$theme" :wishlist-count="$wishlistCount" />
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <x-storefront.footer :footer-menu="$footerMenu" :footer-pages="$footerPages" :theme="$theme" />

    <x-storefront.mobile-bottom-nav :wishlist-count="$wishlistCount" />

    <x-storefront.popup-banner />

    @stack('scripts')
    @livewireScriptConfig
    @include('components.ui.toast-container')
</body>

</html>
