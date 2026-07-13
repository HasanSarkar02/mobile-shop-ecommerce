<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--brand: {{ tenant()->primary_color ?? '#16a34a' }}">

<head>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', tenant()->name)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 antialiased">
    <header class="border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('storefront.home') }}" class="text-xl font-bold">
                @if (tenant()->logo_path)
                    <img src="{{ asset('storage/' . tenant()->logo_path) }}" alt="{{ tenant()->name }}" class="h-8">
                @else
                    {{ tenant()->name }}
                @endif
            </a>
            <button x-data
                @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';"
                class="p-2 rounded-lg border border-gray-300 dark:border-gray-700"
                aria-label="Toggle dark mode">🌓</button>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-gray-200 dark:border-gray-800 mt-16 py-8 text-center text-sm text-gray-500">
        &copy; {{ now()->year }} {{ tenant()->name }}
    </footer>

    @stack('scripts')
</body>

</html>
