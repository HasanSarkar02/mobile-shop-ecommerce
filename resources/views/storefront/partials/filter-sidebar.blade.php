<aside x-data="{ mobileOpen: false }" class="lg:w-64 flex-shrink-0">
    <button @click="mobileOpen = true"
        class="lg:hidden mb-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm font-medium">
        Filters
    </button>

    <div x-show="mobileOpen" x-transition.opacity @click.self="mobileOpen = false"
        class="lg:hidden fixed inset-0 z-50 bg-black/40" style="display: none;">
        <div class="absolute right-0 top-0 h-full w-80 max-w-full bg-white dark:bg-gray-950 p-4 overflow-y-auto"
            @click.stop>
            <div class="flex justify-between items-center mb-4">
                <p class="font-semibold">Filters</p>
                <button @click="mobileOpen = false" class="text-gray-400" aria-label="Close filters">✕</button>
            </div>
            @include('storefront.partials.filter-form')
        </div>
    </div>

    <div class="hidden lg:block sticky top-4">
        @include('storefront.partials.filter-form')
    </div>
</aside>
