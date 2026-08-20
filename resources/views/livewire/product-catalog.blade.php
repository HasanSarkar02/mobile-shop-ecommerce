{{-- resources/views/livewire/product-catalog.blade.php --}}
<div x-data="{ mobileFiltersOpen: false }" wire:loading.class="opacity-60 pointer-events-none"
    wire:target="sort,inStockOnly,emiOnly,warrantyOnly,onSaleOnly,newArrivalOnly,officialOnly,priceMin,priceMax,brandIds,attr,clearFilters,gotoPage">
    <div class="flex items-center justify-between gap-3 mb-4">
        <button type="button" @click="mobileFiltersOpen = true"
            class="lg:hidden inline-flex items-center gap-2 px-4 py-2 rounded-full border border-gray-200 dark:border-gray-800 text-sm font-medium">
            <x-ui.icon name="grid" class="w-4 h-4" />
            Filters
            @if ($this->hasActiveFilters())
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)]"></span>
            @endif
        </button>

        <div class="ml-auto">
            <label for="sort" class="sr-only">Sort by</label>
            <select wire:model.live="sort" id="sort"
                class="rounded-full border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-sm py-2 pl-4 pr-8 focus:border-[var(--brand)] focus:ring-[var(--brand)] transition">
                @foreach (\App\Enums\ProductSortOption::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($this->hasActiveFilters())
        <div class="flex flex-wrap items-center gap-2 mb-5">
            @foreach ($brandIds as $id)
                @php($brand = \App\Models\Brand::find($id))
                @if ($brand)
                    <button wire:click="$set('brandIds', {{ json_encode(array_values(array_diff($brandIds, [$id]))) }})"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                        {{ $brand->name }} <span class="text-gray-400">&times;</span>
                    </button>
                @endif
            @endforeach
            @foreach (['onSaleOnly' => 'On Sale', 'inStockOnly' => 'In Stock', 'emiOnly' => 'EMI Available', 'warrantyOnly' => 'Warranty', 'newArrivalOnly' => 'New Arrival', 'officialOnly' => 'Official Product'] as $prop => $label)
                @if ($this->{$prop})
                    <button wire:click="$set('{{ $prop }}', false)" type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                        {{ $label }} <span class="text-gray-400">&times;</span>
                    </button>
                @endif
            @endforeach
            @if ($priceMin || $priceMax)
                <button wire:click="$set('priceMin', null); $set('priceMax', null)" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    Price Range <span class="text-gray-400">&times;</span>
                </button>
            @endif
            <button wire:click="clearFilters" type="button"
                class="text-sm text-[var(--brand)] font-medium px-1 hover:underline underline-offset-2">Clear
                all</button>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-8">
        {{-- Desktop sidebar --}}
        <aside class="hidden lg:block w-64 flex-shrink-0 space-y-6">
            @include('livewire.partials.catalog-filters', ['facets' => $result['facets']])
        </aside>

        {{-- Mobile filter drawer --}}
        <template x-teleport="body">
            <div x-show="mobileFiltersOpen" x-cloak class="lg:hidden fixed inset-0 z-[60]" role="dialog"
                aria-modal="true" aria-label="Filters">
                <div x-show="mobileFiltersOpen" x-transition.opacity @click="mobileFiltersOpen = false"
                    class="absolute inset-0 bg-black/40"></div>
                <div x-show="mobileFiltersOpen" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="absolute right-0 top-0 h-full w-[85%] max-w-sm bg-white dark:bg-gray-950 shadow-elevated overflow-y-auto p-5">
                    <div class="flex items-center justify-between mb-4">
                        <p class="font-semibold">Filters</p>
                        <button type="button" @click="mobileFiltersOpen = false"
                            class="p-2 -mr-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Close">
                            <x-ui.icon name="close" class="w-5 h-5" />
                        </button>
                    </div>
                    @include('livewire.partials.catalog-filters', ['facets' => $result['facets']])
                    <x-ui.button variant="primary" class="w-full mt-6" @click="mobileFiltersOpen = false">
                        Show {{ $result['products']->total() }} results
                    </x-ui.button>
                </div>
            </div>
        </template>

        <div class="flex-1 min-w-0">
            @if ($result['products']->isEmpty())
                <x-ui.empty-state
                    title="{{ $mode === 'search' ? 'No results for "' . $term . '"' : 'No products found' }}"
                    description="{{ $this->hasActiveFilters() ? 'Try adjusting or clearing your filters.' : 'Check back soon — new products are added regularly.' }}" />
                @if ($this->hasActiveFilters())
                    <div class="text-center -mt-10">
                        <button wire:click="clearFilters" type="button"
                            class="text-sm font-medium text-[var(--brand)] hover:underline underline-offset-2">Clear
                            filters</button>
                    </div>
                @endif
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-8 sm:gap-x-6" wire:loading.remove
                    wire:target="sort,inStockOnly,emiOnly,warrantyOnly,onSaleOnly,newArrivalOnly,officialOnly,priceMin,priceMax,brandIds,attr,clearFilters">
                    @foreach ($cards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>

                <div wire:loading
                    wire:target="sort,inStockOnly,emiOnly,warrantyOnly,onSaleOnly,newArrivalOnly,officialOnly,priceMin,priceMax,brandIds,attr,clearFilters"
                    class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-8 sm:gap-x-6">
                    @for ($i = 0; $i < 6; $i++)
                        <div>
                            <x-ui.skeleton class="aspect-square w-full rounded-2xl" />
                            <x-ui.skeleton class="h-4 w-3/4 mt-3" />
                            <x-ui.skeleton class="h-4 w-1/2 mt-2" />
                        </div>
                    @endfor
                </div>

                <div class="mt-10">
                    {{ $result['products']->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
