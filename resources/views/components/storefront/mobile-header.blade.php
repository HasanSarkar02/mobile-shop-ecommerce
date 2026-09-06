{{-- resources/views/components/storefront/mobile-header.blade.php --}}
@props(['theme', 'headerMenu', 'wishlistCount' => 0])
<div class="lg:hidden" x-data="{ mobileSearchOpen: false }">
    <div class="flex items-center gap-2 h-14 px-3">
        <button type="button" @click="$store.ui.mobileMenuOpen = true"
            class="p-2 -ml-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition" aria-label="Open menu"
            aria-haspopup="true" :aria-expanded="$store.ui.mobileMenuOpen">
            <x-ui.icon name="menu" class="w-6 h-6" />
        </button>

        <a href="{{ route('storefront.home') }}" class="flex-1 flex items-center justify-center gap-2 min-w-0">
            @if ($theme?->logo_path)
                <img src="{{ asset('storage/' . $theme->logo_path) }}" alt="{{ tenant()->name }}" class="h-7 w-auto max-w-[140px] object-contain">
            @else
                <span class="text-sm font-semibold tracking-tight truncate">{{ tenant()->name }}</span>
            @endif
        </a>

        <button type="button" @click="mobileSearchOpen = !mobileSearchOpen"
            class="p-2 -mr-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition" aria-label="Search"
            :aria-expanded="mobileSearchOpen">
            <x-ui.icon name="search" class="w-5 h-5" />
        </button>

        @php $locales = tenant()?->enabledLocales() ?? ['en']; $currentLocale = app()->getLocale(); @endphp
        @if (count($locales) > 1)
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Language"
                    class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                    <x-ui.icon name="globe" class="w-5 h-5" />
                </button>
                <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="absolute right-0 top-full mt-2 w-44 rounded-2xl shadow-elevated bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 py-2 z-50" role="menu">
                    @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $native)
                        @if (in_array($code, $locales, true))
                            <form method="POST" action="{{ route('storefront.locale.switch', ['locale' => $code]) }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                <button type="submit" role="menuitem"
                                    class="w-full flex items-center justify-between px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition {{ $currentLocale === $code ? 'font-semibold text-[var(--brand)]' : 'text-gray-700 dark:text-gray-200' }}">
                                    <span>{{ $code === 'en' ? 'EN' : 'বাংলা' }} — {{ $native }}</span>
                                    @if ($currentLocale === $code)
                                        <svg class="w-4 h-4 text-[var(--brand)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div x-show="mobileSearchOpen" x-cloak x-transition.opacity.duration.150ms
        class="px-3 pb-3 border-b border-gray-100 dark:border-gray-800">
        <livewire:storefront.global-search />
    </div>

    <x-storefront.mobile-menu :header-menu="$headerMenu ?? null" :theme="$theme" :wishlist-count="$wishlistCount ?? 0" />
</div>
