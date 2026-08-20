@props(['headerMenu', 'theme', 'wishlistCount' => 0])
<template x-teleport="body">
    <div x-show="$store.ui.mobileMenuOpen" x-cloak class="lg:hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true"
        aria-label="Menu">
        <div x-show="$store.ui.mobileMenuOpen" x-transition.opacity @click="$store.ui.mobileMenuOpen = false"
            class="absolute inset-0 bg-black/40"></div>

        <div x-show="$store.ui.mobileMenuOpen" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full" @keydown.escape.window="$store.ui.mobileMenuOpen = false"
            class="absolute left-0 top-0 h-full w-[85%] max-w-sm bg-white dark:bg-gray-950 shadow-elevated overflow-y-auto">
            <div class="flex items-center justify-between h-16 px-4 border-b border-gray-100 dark:border-gray-800">
                @if ($theme?->logo_path)
                    <img src="{{ asset('storage/' . $theme->logo_path) }}" alt="{{ tenant()->name }}"
                        class="h-7 w-auto">
                @else
                    <span class="text-base font-semibold tracking-tight">{{ tenant()->name }}</span>
                @endif
                <button type="button" @click="$store.ui.mobileMenuOpen = false"
                    class="p-2 -mr-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    aria-label="Close menu">
                    <x-ui.icon name="close" class="w-5 h-5" />
                </button>
            </div>

            <nav class="py-2" aria-label="Mobile primary">
                @foreach ($headerMenu?->topLevelItems ?? [] as $item)
                    @continue($item->visibility === \App\Enums\Visibility::Desktop)
                    @if ($item->children->isNotEmpty())
                        <div x-data="{ expanded: false }">
                            <button type="button" @click="expanded = !expanded"
                                class="w-full flex items-center justify-between px-4 py-3 text-[15px] font-medium text-gray-800 dark:text-gray-100">
                                <span class="flex items-center gap-2">
                                    {{ $item->label }}
                                    @if ($item->badge_text)
                                        <span
                                            class="text-[10px] font-semibold leading-none bg-[var(--brand)] text-white px-1.5 py-0.5 rounded-full">{{ $item->badge_text }}</span>
                                    @endif
                                </span>
                                <x-ui.icon name="chevron-down" class="w-4 h-4 opacity-60 transition"
                                    x-bind:class="expanded ? 'rotate-180' : ''" />
                            </button>
                            <div x-show="expanded" x-collapse class="bg-gray-50 dark:bg-gray-900">
                                @foreach ($item->children as $child)
                                    @continue($child->visibility === \App\Enums\Visibility::Desktop)
                                    <a href="{{ $child->resolveUrl() ?? '#' }}"
                                        class="block px-8 py-2.5 text-sm text-gray-600 dark:text-gray-300">{{ $child->label }}</a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->resolveUrl() ?? '#' }}"
                            class="flex items-center gap-2 px-4 py-3 text-[15px] font-medium text-gray-800 dark:text-gray-100">
                            {{ $item->label }}
                            @if ($item->badge_text)
                                <span
                                    class="text-[10px] font-semibold leading-none bg-[var(--brand)] text-white px-1.5 py-0.5 rounded-full">{{ $item->badge_text }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="border-t border-gray-100 dark:border-gray-800 py-2">
                <a href="{{ auth('customer')->check() ? route('storefront.account.dashboard') : route('storefront.login') }}"
                    class="flex items-center gap-3 px-4 py-3 text-[15px] font-medium text-gray-800 dark:text-gray-100">
                    <x-ui.icon name="user" class="w-5 h-5 text-gray-400" />
                    {{ auth('customer')->check() ? 'My Account' : 'Sign In' }}
                </a>
                <a href="{{ route('storefront.wishlist') }}"
                    class="flex items-center gap-3 px-4 py-3 text-[15px] font-medium text-gray-800 dark:text-gray-100">
                    <x-ui.icon name="heart" class="w-5 h-5 text-gray-400" />
                    Wishlist
                    <span x-data x-init="$store.wishlist.seedCount({{ $wishlistCount }})"
                        x-show="$store.wishlist.count > 0" x-cloak x-text="$store.wishlist.count"
                        class="ml-auto text-xs font-semibold bg-[var(--brand)] text-white rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $wishlistCount }}</span>
                </a>
                <a href="{{ route('storefront.compare') }}"
                    class="flex items-center gap-3 px-4 py-3 text-[15px] font-medium text-gray-800 dark:text-gray-100">
                    <x-ui.icon name="grid" class="w-5 h-5 text-gray-400" />
                    Compare
                </a>
                <div class="px-2 py-1">
                    <x-storefront.theme-toggle :show-label="true"
                        class="w-full flex items-center px-2 py-3 rounded-lg text-[15px] font-medium text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-900 transition" />
                </div>
            </div>
        </div>
    </div>
</template>
