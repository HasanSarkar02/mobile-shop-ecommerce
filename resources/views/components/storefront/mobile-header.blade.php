{{-- resources/views/components/storefront/mobile-header.blade.php --}}
@props(['theme', 'headerMenu', 'wishlistCount' => 0])
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
        class="px-4 pb-4 border-b border-gray-100 dark:border-gray-800">
        <livewire:storefront.global-search />
    </div>

    <x-storefront.mobile-menu :header-menu="$headerMenu ?? null" :theme="$theme" :wishlist-count="$wishlistCount ?? 0" />
</div>
