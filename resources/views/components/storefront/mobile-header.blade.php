{{-- resources/views/components/storefront/mobile-header.blade.php --}}
@props(['theme', 'headerMenu'])
<div class="lg:hidden" x-data="{ mobileSearchOpen: false }">
    <div class="flex items-center gap-2 h-16 px-4">
        <button type="button" @click="$store.ui.mobileMenuOpen = true"
            class="p-2 -ml-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition" aria-label="Open menu"
            aria-haspopup="true" :aria-expanded="$store.ui.mobileMenuOpen">
            <x-ui.icon name="menu" class="w-6 h-6" />
        </button>

        <a href="{{ route('storefront.home') }}" class="flex-1 flex items-center justify-center gap-2 min-w-0">
            @if ($theme?->logo_path)
                <img src="{{ asset('storage/' . $theme->logo_path) }}" alt="{{ tenant()->name }}" class="h-7 w-auto">
            @else
                <span class="text-base font-semibold tracking-tight truncate">{{ tenant()->name }}</span>
            @endif
        </a>

        <button type="button" @click="mobileSearchOpen = !mobileSearchOpen"
            class="p-2 -mr-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition" aria-label="Search"
            :aria-expanded="mobileSearchOpen">
            <x-ui.icon name="search" class="w-5 h-5" />
        </button>
    </div>

    <div x-show="mobileSearchOpen" x-cloak x-transition.opacity.duration.150ms
        class="px-4 pb-3 border-b border-gray-100 dark:border-gray-800">
        <form action="{{ route('storefront.search') }}" method="GET">
            <label for="mobile-search" class="sr-only">Search products</label>
            <div class="relative">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input id="mobile-search" type="search" name="q" value="{{ request('q') }}"
                    placeholder="Search products, brands..." x-ref="mobileSearchInput" x-init="$watch('mobileSearchOpen', (open) => open && setTimeout(() => $refs.mobileSearchInput.focus(), 150))"
                    class="w-full pl-10 pr-4 py-2.5 rounded-full border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:border-transparent transition">
            </div>
        </form>
    </div>

    <x-storefront.mobile-menu :header-menu="$headerMenu ?? null" :theme="$theme" />
</div>
