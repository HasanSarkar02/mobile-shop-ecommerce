@props(['wishlistCount'])
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800"
    style="padding-bottom: var(--safe-bottom)" aria-label="Mobile primary">
    <div class="flex items-stretch">
        <a href="{{ route('storefront.home') }}"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 text-[11px] font-medium {{ request()->routeIs('storefront.home') ? 'text-[var(--brand)]' : 'text-gray-500 dark:text-gray-400' }}">
            <x-ui.icon name="home" class="w-6 h-6" />
            Home
        </a>

        <button type="button" @click="$store.ui.mobileMenuOpen = true"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400"
            aria-label="Browse categories">
            <x-ui.icon name="grid" class="w-6 h-6" />
            Categories
        </button>

        <livewire:mini-cart variant="badge" />

        <a href="{{ route('storefront.wishlist') }}"
            class="relative flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 text-[11px] font-medium {{ request()->routeIs('storefront.wishlist') ? 'text-[var(--brand)]' : 'text-gray-500 dark:text-gray-400' }}">
            <span class="relative">
                <x-ui.icon name="heart" class="w-6 h-6" />
                @if ($wishlistCount > 0)
                    <span
                        class="absolute -top-1 -right-1.5 bg-[var(--brand)] text-white text-[9px] font-semibold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-0.5">{{ $wishlistCount }}</span>
                @endif
            </span>
            Wishlist
        </a>

        <a href="{{ auth('customer')->check() ? route('storefront.account.dashboard') : route('storefront.login') }}"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 text-[11px] font-medium {{ request()->routeIs('storefront.account.*') ? 'text-[var(--brand)]' : 'text-gray-500 dark:text-gray-400' }}">
            <x-ui.icon name="user" class="w-6 h-6" />
            {{ auth('customer')->check() ? 'Account' : 'Sign In' }}
        </a>
    </div>
</nav>
