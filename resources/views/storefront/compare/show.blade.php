@extends('storefront.layout')

@section('title', 'Compare Products - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => 'Compare products side-by-side by price, specs and availability.',
        'robots' => 'noindex,nofollow',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
        x-data="comparePage(@js($productsJson), @js($rowsJson), {{ $compareCount }}, {{ $compareLimit }})">

        {{-- Page header --}}
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Compare Products</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Compare specifications side-by-side and pick the
                    product that's right for you.</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                    Comparing <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="products.length"></span> of
                    <span x-text="limit"></span>
                </span>
                <button type="button" @click="clearAll()" :disabled="!products.length" x-cloak
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-xl border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600 hover:border-red-300 transition disabled:opacity-40 disabled:pointer-events-none"
                    aria-label="Clear all compared products">
                    <x-ui.icon name="close" class="w-4 h-4" />
                    Clear All
                </button>
            </div>
        </div>

        {{-- Comparison table --}}
        <div x-show="products.length" x-cloak class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft bg-white dark:bg-gray-950">
            <table class="w-full min-w-[720px] text-sm border-collapse">
                <caption class="sr-only">Product comparison</caption>

                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
                        <th scope="col"
                            class="sticky left-0 z-20 w-44 min-w-40 px-4 py-4 text-left align-top bg-gray-50 dark:bg-gray-900">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Specification</span>
                        </th>
                        <template x-for="p in products" :key="p.id">
                            <th scope="col" class="relative px-4 pt-4 pb-3 text-center align-top">
                                <button type="button" @click="remove(p)"
                                    class="absolute top-3 right-3 p-1.5 rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 transition"
                                    :aria-label="'Remove ' + p.name + ' from comparison'" title="Remove">
                                    <x-ui.icon name="close" class="w-4 h-4" />
                                </button>

                                <a :href="p.url" class="block" tabindex="-1">
                                    <template x-if="p.image">
                                        <img :src="p.image" :alt="p.name" width="112" height="112" loading="lazy"
                                            class="w-24 h-24 mx-auto rounded-xl object-cover bg-gray-100 dark:bg-gray-900">
                                    </template>
                                </a>

                                <a :href="p.url"
                                    class="mt-2 block text-sm font-semibold text-gray-900 dark:text-gray-100 line-clamp-2 hover:text-[var(--brand)] transition"
                                    x-text="p.name"></a>

                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" x-text="p.brand || '—'"></p>

                                <div class="mt-2">
                                    <span class="font-bold text-gray-900 dark:text-gray-100" x-text="p.price || '—'"></span>
                                    <template x-if="p.compareAt">
                                        <span class="ml-1 text-xs text-gray-400 line-through" x-text="p.compareAt"></span>
                                    </template>
                                    <template x-if="p.discount">
                                        <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300"
                                            x-text="p.discount + '% OFF'"></div>
                                    </template>
                                </div>

                                <p class="mt-1.5 text-xs font-medium" :class="p.statusClass" x-text="p.statusLabel"></p>

                                <div class="mt-3 flex flex-col gap-1.5">
                                    <template x-if="p.addableVariantId">
                                        <button type="button" @click="addToCart(p)"
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium rounded-xl text-white hover:brightness-110 transition bg-[var(--brand)]"
                                            :aria-label="'Add ' + p.name + ' to cart'">
                                            <x-ui.icon name="cart" class="w-4 h-4" />
                                            Add to Cart
                                        </button>
                                    </template>
                                    <template x-if="!p.addableVariantId && p.variantCount > 1">
                                        <a :href="p.url"
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium rounded-xl text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                            Choose options →
                                        </a>
                                    </template>
                                    <template x-if="!p.addableVariantId && p.variantCount <= 1">
                                        <button type="button" disabled
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium rounded-xl text-gray-400 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">
                                            Out of Stock
                                        </button>
                                    </template>

                                    <a :href="p.url"
                                        class="inline-flex items-center justify-center px-3 py-2 text-xs font-medium rounded-xl text-[var(--brand)] border border-[var(--brand)]/30 hover:bg-[var(--brand)]/5 transition">
                                        View Product
                                    </a>
                                </div>
                            </th>
                        </template>
                    </tr>
                </thead>

                <tbody>
                    <template x-if="rows.length">
                        <template x-for="row in rows" :key="row.label">
                            <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <th scope="row"
                                    class="sticky left-0 z-10 w-44 min-w-40 px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-950">
                                    <span x-text="row.label"></span>
                                </th>
                                <template x-for="p in products" :key="'value-' + p.id">
                                    <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300"
                                        x-text="row.values[p.id] ?? '—'">
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div x-show="!products.length" x-cloak class="py-16">
            <x-ui.empty-state title="Your comparison list is empty"
                description="Add products from any product page to compare them side-by-side."
                actionLabel="Continue shopping" actionUrl="{{ route('storefront.home') }}" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function comparePage(products, rows, initialCount, limit) {
            return {
                products,
                rows,
                limit,

                csrfToken() {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    return meta ? meta.content : '';
                },
                toast(message, type = 'success') {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message, type }
                    }));
                },
                post(url, body) {
                    return fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body),
                        })
                        .then(r => {
                            if (!r.ok) return r.json().then(d => { throw new Error(d.message || 'Something went wrong'); });
                            return r.json();
                        });
                },
                remove(product) {
                    this.post('{{ route('storefront.compare.remove') }}', { product_id: product.id })
                        .then(() => {
                            this.rows.forEach(row => delete row.values[product.id]);
                            this.products = this.products.filter(p => p.id !== product.id);
                            this.toast('Removed from compare');
                            if (window.Livewire) window.Livewire.dispatch('compare-updated');
                        })
                        .catch(e => this.toast(e.message, 'error'));
                },
                clearAll() {
                    this.post('{{ route('storefront.compare.clear') }}', {})
                        .then(() => {
                            this.rows = [];
                            this.products = [];
                            this.toast('Comparison list cleared');
                            if (window.Livewire) window.Livewire.dispatch('compare-updated');
                        })
                        .catch(e => this.toast(e.message, 'error'));
                },
                addToCart(product) {
                    const variantId = product.addableVariantId;
                    if (!variantId) return Promise.resolve();
                    return fetch('{{ route('storefront.cart.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ product_variant_id: variantId, quantity: 1 }),
                        })
                        .then(r => {
                            if (!r.ok) throw new Error('Could not add to cart');
                            return r;
                        })
                        .then(() => {
                            this.toast('Added to cart');
                            if (window.Livewire) window.Livewire.dispatch('cart-updated');
                        })
                        .catch(e => this.toast(e.message, 'error'));
                },
            };
        }
    </script>
@endpush