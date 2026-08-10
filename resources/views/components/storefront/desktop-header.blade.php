@props(['headerMenu', 'theme', 'wishlistCount'])
<div class="hidden lg:block">
    <div class="max-w-7xl mx-auto px-6 xl:px-8">
        <div class="flex items-center gap-8 h-20">
            {{-- Logo --}}
            <a href="{{ route('storefront.home') }}" class="flex-shrink-0 flex items-center gap-2">
                @if ($theme?->logo_path)
                    <img src="{{ asset('storage/' . $theme->logo_path) }}" alt="{{ tenant()->name }}" class="h-9 w-auto">
                @else
                    <span class="text-xl font-semibold tracking-tight">{{ tenant()->name }}</span>
                @endif
            </a>

            {{-- Primary navigation --}}
            <nav class="flex items-center gap-1 text-sm font-medium" aria-label="Primary">
                @foreach ($headerMenu?->topLevelItems ?? [] as $item)
                    @continue($item->visibility === \App\Enums\Visibility::Mobile)
                    <div class="relative group">
                        <a href="{{ $item->resolveUrl() ?? '#' }}"
                            class="flex items-center gap-1 px-3 py-2 rounded-lg text-gray-700 dark:text-gray-200 hover:text-[var(--brand)] hover:bg-gray-50 dark:hover:bg-gray-900 transition">
                            {{ $item->label }}
                            @if ($item->badge_text)
                                <span
                                    class="text-[10px] font-semibold leading-none bg-[var(--brand)] text-white px-1.5 py-0.5 rounded-full">{{ $item->badge_text }}</span>
                            @endif
                            @if ($item->children->isNotEmpty())
                                <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-60" />
                            @endif
                        </a>
                        @if ($item->children->isNotEmpty())
                            <div
                                class="invisible opacity-0 group-hover:visible group-hover:opacity-100 transition absolute top-full left-0 pt-2 z-50">
                                <div
                                    class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-elevated py-2 min-w-48">
                                    @foreach ($item->children as $child)
                                        @continue($child->visibility === \App\Enums\Visibility::Mobile)
                                        <a href="{{ $child->resolveUrl() ?? '#' }}"
                                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:text-[var(--brand)] hover:bg-gray-50 dark:hover:bg-gray-800">{{ $child->label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>

            {{-- Search --}}
            <form action="{{ route('storefront.search') }}" method="GET" class="flex-1 max-w-md ml-auto">
                <label for="desktop-search" class="sr-only">Search products</label>
                <div class="relative">
                    <x-ui.icon name="search"
                        class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input id="desktop-search" type="search" name="q" value="{{ request('q') }}"
                        placeholder="Search products, brands..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-full border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:border-transparent transition">
                </div>
            </form>

            {{-- Actions --}}
            <div class="flex items-center gap-1 flex-shrink-0">
                <x-storefront.theme-toggle />

                <a href="{{ route('storefront.wishlist') }}"
                    class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    aria-label="Wishlist">
                    <x-ui.icon name="heart" class="w-5 h-5" />
                    @if ($wishlistCount > 0)
                        <span
                            class="absolute -top-0.5 -right-0.5 bg-[var(--brand)] text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $wishlistCount }}</span>
                    @endif
                </a>

                <a href="{{ auth('customer')->check() ? route('storefront.account.dashboard') : route('storefront.login') }}"
                    class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    aria-label="{{ auth('customer')->check() ? 'My account' : 'Sign in' }}">
                    <x-ui.icon name="user" class="w-5 h-5" />
                </a>

                <livewire:mini-cart />
            </div>
        </div>
    </div>
</div>
