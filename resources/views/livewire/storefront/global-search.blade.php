{{-- resources/views/livewire/storefront/global-search.blade.php — World-class rich search dropdown --}}
<div
    x-data="globalSearch()"
    @click.outside="open = false"
    @keydown.escape.window="open = false; highlighted = -1"
    class="flex-1 max-w-xl mx-auto relative"
>
    {{-- Input --}}
    <form action="{{ route('storefront.search') }}" method="GET" role="search" @submit.prevent="submit()">
        <label for="global-search-input" class="sr-only">{{ __('Search products') }}</label>
        <div class="relative">
            <x-ui.icon name="search" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />

            <input
                id="global-search-input"
                x-ref="input"
                type="search"
                name="q"
                placeholder="{{ __('Search products, brands...') }}"
                wire:model.live.debounce.300ms="searchQuery"
                @focus="open = true"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="submit()"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open.toString()"
                aria-controls="global-search-dropdown"
                aria-autocomplete="list"
                class="w-full pl-12 pr-16 py-2.5 rounded-full border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:border-transparent transition shadow-sm"
            />

            {{-- Loading spinner --}}
            <div wire:loading wire:target="searchQuery" class="absolute right-20 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            {{-- Clear --}}
            <button
                x-show="$wire.searchQuery && $wire.searchQuery.length > 0"
                x-cloak
                type="button"
                wire:click="clear"
                @click="$nextTick(() => $refs.input.focus())"
                class="absolute right-[5.2rem] top-1/2 -translate-y-1/2 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition"
                aria-label="{{ __('Clear search') }}"
            >
                <x-ui.icon name="close" class="w-4 h-4" />
            </button>

            <button
                type="submit"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 px-4 py-2 rounded-full bg-[var(--brand)] text-white text-sm font-semibold hover:brightness-110 transition"
            >{{ __('Search') }}</button>
        </div>
    </form>

    {{-- Dropdown --}}
    <div
        id="global-search-dropdown"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute left-0 right-0 top-full mt-2 z-50 origin-top"
    >
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-[0_16px_48px_-8px_rgba(0,0,0,0.14),0_4px_12px_-2px_rgba(0,0,0,0.08)] overflow-hidden max-h-[72vh] overflow-y-auto">
            {{-- Wire loading bar --}}
            <div wire:loading wire:target="searchQuery" class="h-0.5 w-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <div class="h-full w-1/3 bg-[var(--brand)] animate-[shimmer_1s_ease_infinite]"></div>
            </div>

            {{-- Empty state — Popular Categories / Trending --}}
            @if (trim($searchQuery) === '' || mb_strlen(trim($searchQuery)) < 2)
                <div class="p-5">
                    @if ($popularCategories->isNotEmpty())
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 mb-3">{{ __('Popular Categories') }}</p>
                        <div class="grid grid-cols-2 gap-2 mb-5">
                            @foreach ($popularCategories as $cat)
                                <a
                                    href="{{ route('storefront.category', $cat->slug) }}"
                                    data-search-link
                                    class="group flex items-center gap-2.5 px-3 py-2.5 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-[var(--brand)]/30 hover:bg-[var(--brand)]/[0.06] transition"
                                >
                                    <span class="w-8 h-8 rounded-lg bg-gray-50 dark:bg-gray-800 group-hover:bg-[var(--brand)]/10 flex items-center justify-center flex-shrink-0 transition">
                                        <x-ui.icon name="grid" class="w-4 h-4 text-gray-400 group-hover:text-[var(--brand)]" />
                                    </span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ $cat->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($trendingSearches->isNotEmpty())
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 mb-2">{{ __('Trending Searches') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($trendingSearches as $t)
                                <a
                                    href="{{ route('storefront.search', ['q' => $t]) }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-300 hover:border-[var(--brand)]/30 hover:text-[var(--brand)] transition"
                                >
                                    <x-ui.icon name="search" class="w-3.5 h-3.5 opacity-60" />
                                    {{ $t }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-2">{{ __('Start typing to search products, brands and categories.') }}</p>
                    @endif
                </div>
            @else
                {{-- Results state --}}
                <div wire:loading.remove wire:target="searchQuery">
                    @if ($products->isEmpty() && $categories->isEmpty() && $brands->isEmpty())
                        <div class="px-6 py-10 text-center">
                            <div class="mx-auto w-12 h-12 rounded-full bg-gray-50 dark:bg-gray-800 flex items-center justify-center mb-3">
                                <x-ui.icon name="search" class="w-6 h-6 text-gray-300" />
                            </div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('No results for') }} "<span class="font-semibold">{{ $searchQuery }}</span>"</p>
                            <p class="text-sm text-gray-400 mt-1">{{ __('Try a different keyword or browse categories.') }}</p>
                            <a href="{{ route('storefront.search', ['q' => $searchQuery]) }}" class="inline-flex mt-4 text-sm font-semibold text-[var(--brand)] hover:underline underline-offset-2">{{ __('View all results') }} →</a>
                        </div>
                    @else
                        <div class="grid md:grid-cols-[1.05fr_1.95fr] divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">
                            {{-- Left: Categories / Brands --}}
                            <div class="p-3 bg-gray-50/60 dark:bg-gray-800/30">
                                @if ($categories->isNotEmpty())
                                    <p class="px-2 pt-1 pb-2 text-[11px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Categories') }}</p>
                                    <ul class="space-y-1" role="listbox" aria-label="{{ __('Categories') }}">
                                        @foreach ($categories as $c)
                                            <li>
                                                <a
                                                    href="{{ $c['url'] }}"
                                                    data-search-link
                                                    role="option"
                                                    class="search-link flex items-center justify-between px-2.5 py-2 rounded-xl text-sm text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 hover:shadow-sm border border-transparent hover:border-gray-100 dark:hover:border-gray-700 transition"
                                                >
                                                    <span class="truncate font-medium">{{ $c['name'] }}</span>
                                                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($brands->isNotEmpty())
                                    <p class="px-2 pt-3 pb-2 text-[11px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Brands') }}</p>
                                    <ul class="space-y-1" role="listbox" aria-label="{{ __('Brands') }}">
                                        @foreach ($brands as $b)
                                            <li>
                                                <a
                                                    href="{{ $b['url'] }}"
                                                    data-search-link
                                                    role="option"
                                                    class="search-link flex items-center justify-between px-2.5 py-2 rounded-xl text-sm text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 hover:shadow-sm border border-transparent hover:border-gray-100 dark:hover:border-gray-700 transition"
                                                >
                                                    <span class="truncate font-medium">{{ $b['name'] }}</span>
                                                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($categories->isEmpty() && $brands->isEmpty())
                                    <p class="px-2 py-6 text-sm text-gray-400 text-center">{{ __('No categories or brands found.') }}</p>
                                @endif

                                <div class="mt-3 px-2">
                                    <a href="{{ route('storefront.search', ['q' => $searchQuery]) }}" class="text-xs font-semibold text-[var(--brand)] hover:underline underline-offset-2">{{ __('View all results for') }} "{{ $searchQuery }}" →</a>
                                </div>
                            </div>

                            {{-- Right: Products --}}
                            <div class="p-3">
                                <p class="px-2 pt-1 pb-2 text-[11px] font-semibold uppercase tracking-widest text-gray-400">{{ __('Products') }}</p>
                                @if ($products->isNotEmpty())
                                    <ul class="space-y-1" role="listbox" aria-label="{{ __('Products') }}">
                                        @foreach ($products as $p)
                                            <li>
                                                <a
                                                    href="{{ $p['url'] }}"
                                                    data-search-link
                                                    role="option"
                                                    class="search-link flex items-center gap-3 px-2.5 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 border border-transparent hover:border-gray-100 dark:hover:border-gray-700 transition"
                                                >
                                                    @if ($p['thumb'])
                                                        <img src="{{ $p['thumb'] }}" alt="" loading="lazy" class="w-11 h-11 rounded-xl object-cover bg-gray-100 dark:bg-gray-800 flex-shrink-0 border border-gray-100 dark:border-gray-700" />
                                                    @else
                                                        <span class="w-11 h-11 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center flex-shrink-0 border border-gray-100 dark:border-gray-700">
                                                            <x-ui.icon name="image" class="w-5 h-5 text-gray-300" />
                                                        </span>
                                                    @endif
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-[13px] font-medium leading-snug text-gray-900 dark:text-gray-100 line-clamp-1">{{ $p['name'] }}</span>
                                                        <span class="block text-xs text-gray-400">{{ __('View product') }}</span>
                                                    </span>
                                                    <span class="text-sm font-semibold text-[var(--brand)] flex-shrink-0">{{ money_without_trailing_zeros((int) round($p['price'] * 100)) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="px-2 py-6 text-sm text-gray-400 text-center">{{ __('No products matched.') }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Loading skeleton while debouncing --}}
                <div wire:loading wire:target="searchQuery" class="p-4 space-y-3">
                    <div class="h-4 w-24 bg-gray-100 dark:bg-gray-800 rounded animate-pulse"></div>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-3/4 bg-gray-100 dark:bg-gray-800 rounded animate-pulse"></div>
                                <div class="h-3 w-1/3 bg-gray-100 dark:bg-gray-800 rounded animate-pulse"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    function globalSearch() {
        return {
            open: false,
            highlighted: -1,
            init() {
                this.$watch('$wire.searchQuery', () => {
                    this.highlighted = -1;
                    if (this.$wire.searchQuery && this.$wire.searchQuery.trim().length >= 2) {
                        this.open = true;
                    }
                });
            },
            links() {
                const root = this.$el.querySelector('#global-search-dropdown');
                if (!root) return [];
                return [...root.querySelectorAll('[data-search-link]')];
            },
            move(dir) {
                const items = this.links();
                if (!items.length) return;
                if (!this.open) this.open = true;
                this.highlighted = (this.highlighted + dir + items.length) % items.length;
                items.forEach((el, i) => {
                    el.classList.toggle('!bg-gray-50', i === this.highlighted);
                    el.classList.toggle('dark:!bg-gray-800', i === this.highlighted);
                    el.classList.toggle('!border-gray-100', i === this.highlighted);
                    el.classList.toggle('dark:!border-gray-700', i === this.highlighted);
                    el.classList.toggle('!shadow-sm', i === this.highlighted);
                });
                const active = items[this.highlighted];
                if (active) active.scrollIntoView({ block: 'nearest' });
            },
            submit() {
                const items = this.links();
                if (this.highlighted >= 0 && items[this.highlighted]) {
                    window.location.href = items[this.highlighted].getAttribute('href');
                    return;
                }
                const q = this.$wire.searchQuery ?? '';
                if (q.trim().length) {
                    const base = @js(route('storefront.search'));
                    window.location.href = base + '?q=' + encodeURIComponent(q.trim());
                }
            },
        }
    }
</script>
@endpush
