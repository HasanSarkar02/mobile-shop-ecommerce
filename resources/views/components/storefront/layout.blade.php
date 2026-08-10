<!DOCTYPE html>
{{-- resources/views/storefront/layout.blade.php --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--brand: {{ $theme?->primary_color ?? '#16a34a' }}">

<head>
    {{-- Runs before CSS paints to avoid a flash of the wrong theme; must stay
         inline and above @vite (localStorage/matchMedia aren't available at
         build time, and this needs to execute before first paint). --}}
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia(
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

<body class="bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 antialiased pb-16 lg:pb-0">
    <x-storefront.announcement-bar :announcement="$announcement" />

    <header
        class="sticky top-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-b border-gray-200 dark:border-gray-800">
        <x-storefront.desktop-header :header-menu="$headerMenu" :theme="$theme" :wishlist-count="$wishlistCount" />
        <x-storefront.mobile-header :header-menu="$headerMenu" :theme="$theme" />
    </header>

    <main>
        @yield('content')
    </main>

    <x-storefront.footer :footer-menu="$footerMenu" :footer-pages="$footerPages" :theme="$theme" />

    <x-storefront.mobile-bottom-nav :wishlist-count="$wishlistCount" />

    @stack('scripts')
    @livewireScriptConfig
    @include('components.ui.toast-container')
</body>

</html>
