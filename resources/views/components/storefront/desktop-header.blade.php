@props(['headerMenu', 'headerCategories', 'theme', 'wishlistCount'])
<div class="hidden lg:block">
    {{-- Row 1: brand + search + actions --}}
    <div class="max-w-7xl mx-auto px-6 xl:px-8">
        <div class="flex items-center gap-6 h-16">
            {{-- Logo --}}
            <a href="{{ route('storefront.home') }}" class="flex-shrink-0 flex items-center gap-2">
                @if ($theme?->logo_path)
                    <img src="{{ asset('storage/' . $theme->logo_path) }}" alt="{{ tenant()->name }}" class="h-9 w-auto">
                @else
                    <span class="text-lg font-bold tracking-tight">{{ tenant()->name }}</span>
                @endif
            </a>

            {{-- Search with live suggestions --}}
            <div x-data="headerSearch()" @click.outside="open = false" class="flex-1 max-w-xl mx-auto relative">
                <form action="{{ route('storefront.search') }}" method="GET" role="search" @submit="open = false">
                    <label for="desktop-search" class="sr-only">Search products</label>
                    <div class="relative">
                        <x-ui.icon name="search"
                            class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input id="desktop-search" type="search" name="q" value="{{ request('q') }}"
                            placeholder="Search products, brands..."
                            x-model="term"
                            @input.debounce.250ms="suggest()"
                            @keydown.enter.prevent="submit()"
                            @keydown.down.prevent="move(1)"
                            @keydown.up.prevent="move(-1)"
                            @focus="if (term.trim().length >= 2) open = true"
                            autocomplete="off" role="combobox" aria-expanded="false" :aria-expanded="open.toString()"
                            class="w-full pl-12 pr-16 py-2.5 rounded-full border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:border-transparent transition shadow-sm">
                        <button type="submit"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 px-4 py-2 rounded-full bg-[var(--brand)] text-white text-sm font-semibold hover:brightness-110 transition">
                            Search
                        </button>
                    </div>
                </form>

                {{-- Suggestions dropdown --}}
                <div x-show="open && term.trim().length >= 2" x-cloak x-transition.opacity.scale.origin.top
                    class="absolute left-0 right-0 top-full mt-2 z-50">
                    <div
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-elevated overflow-hidden max-h-[70vh] overflow-y-auto">
                        <div x-show="loading" x-cloak class="px-4 py-6 text-sm text-gray-400 text-center">
                            Searching…
                        </div>

                        <template x-if="!loading && results.products.length">
                            <div>
                                <p class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                    Products</p>
                                <template x-for="(p, i) in results.products" :key="p.url">
                                    <a :href="p.url" @mouseenter="highlight(i)"
                                        :class="highlighted === i ? 'bg-gray-50 dark:bg-gray-800' : ''"
                                        class="flex items-center gap-3 px-4 py-2.5 transition">
                                        <img :src="p.thumb" alt="" loading="lazy"
                                            class="w-10 h-10 rounded-lg object-cover bg-gray-100 dark:bg-gray-800 flex-shrink-0">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-gray-900 dark:text-gray-100 truncate"
                                                x-text="p.name"></p>
                                        </div>
                                        <span class="text-sm font-semibold text-[var(--brand)] flex-shrink-0"
                                            x-text="p.price ? '৳' + p.price.toLocaleString() : ''"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="!loading && results.categories.length">
                            <div class="py-1 border-t border-gray-100 dark:border-gray-800">
                                <p class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                    Categories</p>
                                <template x-for="(c, i) in results.categories" :key="c.url">
                                    <a :href="c.url" @mouseenter="highlight(results.products.length + i)"
                                        :class="highlighted === results.products.length + i ? 'bg-gray-50 dark:bg-gray-800' : ''"
                                        class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 dark:text-gray-200 transition">
                                        <span x-text="c.name"></span>
                                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-gray-400" />
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="!loading && results.brands.length">
                            <div class="py-1 border-t border-gray-100 dark:border-gray-800">
                                <p class="px-4 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                    Brands</p>
                                <template x-for="(b, i) in results.brands" :key="b.url">
                                    <a :href="b.url" @mouseenter="highlight(results.products.length + results.categories.length + i)"
                                        :class="highlighted === results.products.length + results.categories.length + i ? 'bg-gray-50 dark:bg-gray-800' : ''"
                                        class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 dark:text-gray-200 transition">
                                        <span x-text="b.name"></span>
                                        <x-ui.icon name="chevron-right" class="w-4 h-4 text-gray-400" />
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="!loading && !results.products.length && !results.categories.length && !results.brands.length">
                            <div class="px-4 py-6 text-sm text-gray-400 text-center">
                                No results found for "<span x-text="term"></span>"
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 flex-shrink-0">
                <x-storefront.theme-toggle />

                <livewire:compare-badge />

                <a href="{{ route('storefront.wishlist') }}"
                    class="relative p-2.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    aria-label="Wishlist">
                    <x-ui.icon name="heart" class="w-[22px] h-[22px]" />
                    <span x-data x-init="$store.wishlist.seedCount({{ $wishlistCount }})"
                        x-show="$store.wishlist.count > 0" x-cloak x-text="$store.wishlist.count"
                        class="absolute -top-0.5 -right-0.5 bg-[var(--brand)] text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $wishlistCount }}</span>
                </a>

                <a href="{{ auth('customer')->check() ? route('storefront.account.dashboard') : route('storefront.login') }}"
                    class="flex items-center gap-2 px-3 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    aria-label="{{ auth('customer')->check() ? 'My account' : 'Sign in' }}">
                    <x-ui.icon name="user" class="w-[22px] h-[22px]" />
                    <span class="hidden 2xl:inline text-sm font-medium">
                        {{ auth('customer')->check() ? 'My Account' : 'Sign In' }}
                    </span>
                </a>

                <livewire:mini-cart />
            </div>
        </div>
    </div>

    {{-- Row 2: category navigation bar --}}
    <div class="bg-[var(--brand)]">
        <div class="max-w-7xl mx-auto px-6 xl:px-8">
            <div class="flex items-center h-12 gap-1">
                {{-- All Categories mega menu trigger --}}
                <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                    @click.outside="open = false" @scroll.window="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                        class="flex items-center gap-2 px-4 h-12 text-white font-semibold text-sm rounded-none hover:bg-white/10 transition"
                        aria-label="All categories">
                        <x-ui.icon name="menu" class="w-5 h-5" />
                        <span>All Categories</span>
                        <x-ui.icon name="chevron-down"
                            class="w-4 h-4 opacity-70 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute left-0 top-full pt-0 z-50 w-[720px] max-w-[calc(100vw-4rem)]">
                        <div
                            class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl rounded-tl-none shadow-elevated p-5 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4 max-h-[70vh] overflow-y-auto">
                            @forelse ($headerCategories as $category)
                                <div>
                                    <a href="{{ route('storefront.category', $category->slug) }}"
                                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-white hover:text-[var(--brand)] transition">
                                        {{ $category->name }}
                                        @if ($category->products_count > 0)
                                            <span
                                                class="text-xs font-normal text-gray-400">({{ $category->products_count }})</span>
                                        @endif
                                    </a>
                                    @if ($category->children->isNotEmpty())
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($category->children as $child)
                                                <li>
                                                    <a href="{{ route('storefront.category', $child->slug) }}"
                                                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-[var(--brand)] transition">
                                                        {{ $child->name }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-400 col-span-full">No categories yet.</p>
                            @endforelse
                            @if ($headerCategories->isNotEmpty())
                                <div class="col-span-full border-t border-gray-100 dark:border-gray-800 pt-3">
                                    <a href="{{ route('storefront.categories.index') }}"
                                        class="text-sm font-semibold text-[var(--brand)] hover:underline underline-offset-2">
                                        View all categories →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <span class="w-px h-6 bg-white/25 flex-shrink-0"></span>

                {{-- Primary navigation --}}
                <nav class="flex items-center gap-0.5 flex-1 min-w-0" aria-label="Primary">
                    @foreach ($headerMenu?->topLevelItems ?? [] as $item)
                        @continue($item->visibility === \App\Enums\Visibility::Mobile)
                        <div class="relative group">
                            <a href="{{ $item->resolveUrl() ?? '#' }}"
                                class="flex items-center gap-1 px-3 h-12 text-white text-sm font-medium hover:bg-white/10 transition whitespace-nowrap">
                                {{ $item->label }}
                                @if ($item->badge_text)
                                    <span
                                        class="text-[10px] font-semibold leading-none bg-white text-[var(--brand)] px-1.5 py-0.5 rounded-full">{{ $item->badge_text }}</span>
                                @endif
                                @if ($item->children->isNotEmpty())
                                    <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-70" />
                                @endif
                            </a>
                            @if ($item->children->isNotEmpty())
                                <div
                                    class="invisible opacity-0 group-hover:visible group-hover:opacity-100 transition absolute top-full left-0 pt-1 z-50">
                                    <div
                                        class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl rounded-tl-none shadow-elevated py-2 min-w-48">
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

                {{-- Right side: help line --}}
                @if (tenant()->contact_phone)
                    <a href="tel:{{ tenant()->contact_phone }}"
                        class="flex items-center gap-2 px-3 h-12 text-white text-sm font-medium hover:bg-white/10 transition flex-shrink-0"
                        aria-label="Call us">
                        <x-ui.icon name="phone" class="w-4 h-4 opacity-80" />
                        <span class="hidden xl:inline tracking-wide">{{ tenant()->contact_phone }}</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function headerSearch() {
            return {
                term: '',
                open: false,
                loading: false,
                results: { products: [], categories: [], brands: [] },
                highlighted: -1,

                allLinks() {
                    return [...this.results.products, ...this.results.categories, ...this.results.brands];
                },
                suggest() {
                    if (this.term.trim().length < 2) {
                        this.open = false;
                        return;
                    }
                    this.loading = true;
                    fetch('{{ route('storefront.search.suggest') }}?q=' + encodeURIComponent(this.term), {
                            headers: { 'Accept': 'application/json' }
                        })
                        .then(r => r.json())
                        .then(data => {
                            this.results = {
                                products: data.products ?? [],
                                categories: data.categories ?? [],
                                brands: data.brands ?? []
                            };
                            this.highlighted = -1;
                            this.open = true;
                        })
                        .finally(() => this.loading = false);
                },
                move(dir) {
                    const total = this.allLinks().length;
                    if (!total) return;
                    this.highlighted = (this.highlighted + dir + total) % total;
                },
                highlight(i) {
                    this.highlighted = i;
                },
                submit() {
                    if (this.highlighted >= 0) {
                        const target = this.allLinks()[this.highlighted];
                        if (target) {
                            window.location.href = target.url;
                            return;
                        }
                    }
                    this.open = false;
                    event.target.closest('form').submit();
                }
            }
        }
    </script>
@endpush
