@extends('storefront.layout')

@section('title', ($product->translation('en')?->name ?? 'Product') . ' - ' . tenant()->name)

@section('content')
    @php
        $productDescription = $product->translation('en')?->sanitizedDescription();
        $showSpecifications = $specificationGroups->isNotEmpty();
        $showDescription = filled($productDescription);
        $showReviews = $product->reviews_count > 0;
        $showFaqs = $product->faqs->isNotEmpty();
        $navSections = collect([
            'specifications' => $showSpecifications,
            'description' => $showDescription,
            'reviews' => $showReviews,
            'faq' => $showFaqs,
        ])->filter()->keys()->values();
        $navLabels = [
            'specifications' => 'Specifications',
            'description' => 'Description',
            'reviews' => 'Reviews',
            'faq' => 'FAQ',
        ];
    @endphp

    @include('storefront.partials.seo-meta', [
        'description' => $product->translation('en')?->meta_description,
        'canonical' => route('storefront.product', $product->translation('en')?->slug),
    ])

    @push('meta')
        <script type="application/ld+json">{!! json_encode($productJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($faqJsonLd)
            <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        <style>
            html {
                scroll-behavior: smooth;
            }
            @media (prefers-reduced-motion: reduce) {
                html {
                    scroll-behavior: auto;
                }
            }
        </style>
    @endpush

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 lg:pb-8"
        x-data="productDetail(@js($variantsData), @js($productImages), @js($dimensions), {{ $product->variants->first()?->id ?? 'null' }}, @js($isWishlisted), @js($isComparing))"
        x-init="init()">
        <nav class="text-sm text-gray-500 mb-6" aria-label="Breadcrumb">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            @if ($product->category)
                <span class="mx-1">/</span>
                <a href="{{ route('storefront.category', $product->category->slug) }}"
                    class="hover:text-[var(--brand)]">{{ $product->category->name }}</a>
            @endif
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $product->translation('en')?->name }}</span>
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            {{-- Gallery --}}
            <div>
                <button type="button" @click="lightboxOpen = true"
                    class="block w-full aspect-square rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-900 relative group cursor-zoom-in"
                    aria-label="Open full-size image">
                    <template x-for="image in currentImages()" :key="image">
                        <img x-show="image === activeImage" :src="image" width="600" height="600"
                            class="w-full h-full object-cover">
                    </template>
                    <span
                        class="absolute bottom-3 right-3 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft opacity-0 group-hover:opacity-100 transition">
                        <x-ui.icon name="search" class="w-4 h-4" />
                    </span>
                </button>
                <div class="flex gap-2 mt-4 overflow-x-auto pb-1">
                    <template x-for="image in currentImages()" :key="'thumb-' + image">
                        <button @click="activeImage = image"
                            class="w-16 h-16 rounded-lg overflow-hidden border-2 flex-shrink-0 transition"
                            :class="activeImage === image ? 'border-[var(--brand)]' : 'border-transparent'">
                            <img :src="image" width="64" height="64" loading="lazy"
                                class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Lightbox --}}
            <template x-teleport="body">
                <div x-show="lightboxOpen" x-cloak class="fixed inset-0 z-[70] bg-black/90 flex items-center justify-center p-4 sm:p-10"
                    role="dialog" aria-modal="true" aria-label="Product image" @keydown.escape.window="lightboxOpen = false"
                    @click="lightboxOpen = false">
                    <button type="button" @click.stop="lightboxOpen = false"
                        class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition"
                        aria-label="Close">
                        <x-ui.icon name="close" class="w-6 h-6" />
                    </button>
                    <template x-for="image in currentImages()" :key="'lightbox-' + image">
                        <img x-show="image === activeImage" :src="image" @click.stop
                            class="max-w-full max-h-full object-contain rounded-lg">
                    </template>
                    <template x-if="currentImages().length > 1">
                        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2" @click.stop>
                            <template x-for="image in currentImages()" :key="'lightbox-dot-' + image">
                                <button @click="activeImage = image" class="w-2 h-2 rounded-full transition"
                                    :class="activeImage === image ? 'bg-white' : 'bg-white/40'"
                                    aria-label="Show image"></button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Details --}}
            <div>
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($product->brand)
                                <p class="text-sm text-gray-500">{{ $product->brand->name }}</p>
                            @endif
                            @if ($product->is_official_import)
                                <x-ui.badge variant="neutral">Official Product</x-ui.badge>
                            @endif
                        </div>
                        <h1 class="text-2xl font-bold mt-1">{{ $product->translation('en')?->name }}</h1>
                        @if ($product->reviews_count > 0)
                            <div class="mt-2">
                                <x-ui.rating-stars :rating="$product->average_rating" :count="$product->reviews_count" />
                            </div>
                        @endif
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <button @click="toggleWishlist()" :disabled="wishlistLoading"
                            class="p-2.5 rounded-xl border transition"
                            :class="wishlisted ? 'border-red-300 bg-red-50 dark:bg-red-950 text-red-600' :
                                'border-gray-300 dark:border-gray-700'"
                            aria-label="Toggle wishlist" :aria-pressed="wishlisted">
                            <x-ui.icon name="heart" :solid="true" class="w-5 h-5" x-bind:class="wishlisted ? '' : 'opacity-40'" />
                        </button>
                        <button @click="toggleCompare()" :disabled="compareLoading"
                            class="p-2.5 rounded-xl border transition text-sm"
                            :class="comparing ? 'border-[var(--brand)] bg-[var(--brand)]/10 text-[var(--brand)]' :
                                'border-gray-300 dark:border-gray-700'"
                            aria-label="Toggle compare" :aria-pressed="comparing">
                            <x-ui.icon name="grid" class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <div x-show="!current()" x-cloak
                    class="mt-4 rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 p-3 text-sm text-amber-700 dark:text-amber-300">
                    This combination of options is not available.
                </div>

                <template x-if="current()">
                    <div>
                        <div class="mt-4 flex items-baseline gap-2 flex-wrap">
                            <span class="font-bold text-3xl" x-text="formatPrice(current().price)"></span>
                            <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                                <span class="text-gray-400 line-through text-sm"
                                    x-text="formatPrice(current().compare_at_price)"></span>
                            </template>
                            <template x-if="discountPercent()">
                                <x-ui.badge variant="danger"><span x-text="discountPercent() + '% OFF'"></span></x-ui.badge>
                            </template>
                        </div>

                        <p class="text-sm mt-2 font-medium"
                            :class="current().availability === 'out_of_stock' ? 'text-red-500' : (current()
                                .fulfillment_strategy === 'preorder' ? 'text-amber-600' : 'text-green-600')"
                            x-text="availabilityLabel()"></p>
                        <template x-if="current().low_stock_remaining">
                            <p class="text-sm mt-0.5 text-amber-600 font-medium"
                                x-text="'Only ' + current().low_stock_remaining + ' left in stock'"></p>
                        </template>
                    </div>
                </template>

                <template x-for="dimension in dimensions" :key="dimension.code">
                    <div class="mt-4">
                        <p class="text-sm font-medium mb-2" x-text="dimension.label"></p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="option in dimensionOptions(dimension.code)" :key="dimension.code + '-' + option">
                                <button @click="selected[dimension.code] = option; updateVariant()"
                                    class="px-3 py-1.5 rounded-full border text-sm transition"
                                    :class="selected[dimension.code] === option ?
                                        'border-[var(--brand)] text-[var(--brand)] bg-[var(--brand)]/10' :
                                        'border-gray-300 dark:border-gray-700 hover:border-gray-400'"
                                    x-text="option + (dimension.suffix || '')"></button>
                            </template>
                        </div>
                    </div>
                </template>

                @if ($product->emiPlans->isNotEmpty())
                    <template x-if="current()">
                        <x-ui.alert variant="info" class="mt-6">
                            <p class="font-medium mb-2">EMI Available</p>
                            <ul class="space-y-1">
                                @foreach ($product->emiPlans as $plan)
                                    <li>
                                        {{ $plan->bank_name }} — {{ $plan->tenure_months }} months
                                        (~<span
                                            x-text="formatPrice(Math.round(current().price * (1 + {{ $plan->interest_rate }} / 100) / {{ $plan->tenure_months }}))"></span>/mo)
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    </template>
                @endif

                <div class="flex items-center gap-3 mt-6">
                    <div class="flex items-center border border-gray-300 dark:border-gray-700 rounded-xl">
                        <button @click="quantity = Math.max(1, quantity - 1)"
                            class="w-10 h-11 flex items-center justify-center text-lg"
                            aria-label="Decrease quantity">−</button>
                        <span class="w-10 text-center" x-text="quantity"></span>
                        <button @click="quantity++" class="w-10 h-11 flex items-center justify-center text-lg"
                            aria-label="Increase quantity">+</button>
                    </div>
                    <x-ui.button variant="primary" size="lg" class="flex-1" @click="addToCart()"
                        x-bind:disabled="cartLoading || !current() || current().availability === 'out_of_stock'">
                        <span
                            x-text="cartLoading ? 'Adding…' : (!current() ? 'Unavailable' : (current().availability === 'out_of_stock' ? 'Out of Stock' : (current().fulfillment_strategy === 'preorder' ? 'Pre-Order Now' : 'Add to Cart')))"></span>
                    </x-ui.button>
                </div>
            </div>
        </div>

        {{-- Sticky mobile add-to-cart bar — sits above the persistent mobile
             bottom nav (bottom-16 ≈ its height), not overlapping it. Only the
             bottom nav itself needs safe-area padding, since it's the
             bottommost fixed element on the page. --}}
        <template x-if="current()">
            <div
                class="lg:hidden fixed bottom-16 inset-x-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center gap-3">
                <div class="min-w-0 flex-shrink-0">
                    <p class="font-bold text-lg leading-none" x-text="formatPrice(current().price)"></p>
                    <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                        <p class="text-xs text-gray-400 line-through leading-none mt-1"
                            x-text="formatPrice(current().compare_at_price)"></p>
                    </template>
                </div>
                <x-ui.button variant="primary" size="lg" class="flex-1" @click="addToCart()"
                    x-bind:disabled="cartLoading || current().availability === 'out_of_stock'">
                    <span
                        x-text="cartLoading ? 'Adding…' : (current().availability === 'out_of_stock' ? 'Out of Stock' : (current().fulfillment_strategy === 'preorder' ? 'Pre-Order Now' : 'Add to Cart'))"></span>
                </x-ui.button>
            </div>
        </template>

        @if ($navSections->isNotEmpty())
            <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections"
                class="sticky top-16 lg:top-28 z-30 -mx-4 sm:mx-0 mt-8 lg:mt-12 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
                <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
                    @foreach ($navSections as $sectionId)
                        <a href="#{{ $sectionId }}"
                            class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10"
                            :class="isActive('{{ $sectionId }}') ? 'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : ''"
                            :aria-current="isActive('{{ $sectionId }}') ? 'location' : null">
                            {{ $navLabels[$sectionId] ?? ucfirst($sectionId) }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif

        {{-- Specifications --}}
        <section id="specifications" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="specifications-heading">
            <h2 id="specifications-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Specifications</h2>
            <div class="mt-5">
                @forelse($specificationGroups as $group)
                    <div class="mb-8 last:mb-0">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                            {{ $group['group'] }}
                        </h3>
                        <dl class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-100 dark:border-gray-800">
                            @foreach ($group['items'] as $value)
                                <div class="flex py-2.5 text-sm gap-4 px-4">
                                    <dt class="w-1/3 flex-shrink-0 text-gray-500">{{ $value->attributeDefinition->label }}</dt>
                                    <dd class="text-gray-800 dark:text-gray-200">{{ $value->displayValue() }}
                                        @if ($value->attributeDefinition->unit)
                                            <span class="text-gray-500">{{ $value->attributeDefinition->unit }}</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No specifications listed yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Description --}}
        @if ($showDescription)
            <section id="description" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="description-heading">
                <h2 id="description-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Description</h2>
                <div class="prose dark:prose-invert max-w-none mt-5">
                    {!! $productDescription !!}
                </div>
            </section>
        @endif

        {{-- Warranty --}}
        @if ($product->translation('en')?->warranty_info)
            <section id="warranty" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="warranty-heading">
                <h2 id="warranty-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Warranty</h2>
                <div class="prose dark:prose-invert max-w-none mt-5">
                    {!! nl2br(e($product->translation('en')->warranty_info)) !!}
                </div>
            </section>
        @endif

        {{-- Reviews --}}
        <section id="reviews" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="reviews-heading">
            <h2 id="reviews-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Reviews ({{ $product->reviews_count }})</h2>

            @if ($product->reviews_count > 0)
                <div class="mb-6 mt-5"><x-ui.rating-stars :rating="$product->average_rating" :count="$product->reviews_count" /></div>
            @endif

            <div class="space-y-4 mt-5">
                @forelse($product->approvedReviews as $review)
                    <div class="border border-gray-100 dark:border-gray-800 rounded-xl p-4">
                        <div class="flex justify-between items-start">
                            <p class="font-medium">{{ $review->customer->name }}</p>
                            @if ($review->is_verified_purchase)
                                <x-ui.badge variant="success">Verified Purchase</x-ui.badge>
                            @endif
                        </div>
                        <x-ui.rating-stars :rating="$review->rating" />
                        @if ($review->title)
                            <p class="font-medium mt-2">{{ $review->title }}</p>
                        @endif
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $review->body }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No reviews yet — be the first to review this product.</p>
                @endforelse
            </div>

            @auth('customer')
                <form method="POST" action="{{ route('storefront.product.reviews.store', $product) }}"
                    class="mt-6 space-y-3 max-w-lg">
                    @csrf
                    <x-ui.select name="rating" label="Rating" required>
                        <option value="">Select a rating</option>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ $i }} Stars</option>
                        @endfor
                    </x-ui.select>
                    <x-ui.input name="title" label="Title (optional)" />
                    <x-ui.textarea name="body" label="Your review" required />
                    <x-ui.button type="submit" variant="primary">Submit Review</x-ui.button>
                </form>
            @else
                <p class="text-sm text-gray-500 mt-6"><a href="{{ route('storefront.login') }}"
                        class="text-[var(--brand)] font-medium">Log in</a> to write a review.</p>
            @endauth
        </section>

        {{-- FAQ --}}
        @if ($showFaqs)
            <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="faq-heading">
                <h2 id="faq-heading" class="text-xl lg:text-2xl font-bold tracking-tight">FAQ</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($product->faqs as $faq)
                        <div x-data="{ expanded: false }" class="border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                            <button @click="expanded = !expanded"
                                class="w-full text-left font-medium flex justify-between items-center">
                                {{ $faq->question }}
                                <span x-text="expanded ? '−' : '+'" class="text-gray-400"></span>
                            </button>
                            <div x-show="expanded" x-collapse class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ $faq->answer }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Related --}}
        @if ($relatedProducts->isNotEmpty())
            <section id="related" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="related-heading">
                <h2 id="related-heading" class="text-xl lg:text-2xl font-bold tracking-tight">You May Also Like</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($relatedProducts as $related)
                        @include('storefront.partials.product-card', ['product' => $related])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        /**
         * Scrollspy for the sticky product-section navigation. Tracks the
         * section currently crossing the middle of the viewport and reflects it
         * via aria-current + the active link styling. Degrades gracefully: with
         * JS disabled the plain anchors still jump to the server-rendered
         * sections.
         */
        function productSections(ids) {
            return {
                ids,
                activeId: ids.length ? ids[0] : '',
                init() {
                    if (!('IntersectionObserver' in window)) return;

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                this.activeId = entry.target.id;
                            }
                        });
                    }, { rootMargin: '-45% 0px -50% 0px' });

                    this.ids.forEach((id) => {
                        const el = document.getElementById(id);
                        if (el) observer.observe(el);
                    });
                },
                isActive(id) {
                    return this.activeId === id;
                },
            };
        }

        function productDetail(variants, productImages, dimensions, initialId, initialWishlisted, initialComparing) {
            return {
                variants,
                productImages,
                dimensions,
                selected: {},
                activeImage: null,
                lightboxOpen: false,
                currentVariantId: initialId,
                unavailable: false,
                quantity: 1,
                wishlisted: initialWishlisted,
                comparing: initialComparing,
                cartLoading: false,
                wishlistLoading: false,
                compareLoading: false,

                init() {
                    const first = this.current();

                    if (! first) {
                        this.unavailable = true;
                        this.currentVariantId = null;
                        this.activeImage = this.productImages[0] ?? null;

                        return;
                    }

                    dimensions.forEach(d => {
                        if (first.dims[d.code] !== undefined && first.dims[d.code] !== null) {
                            this.selected[d.code] = first.dims[d.code];
                        }
                    });

                    this.activeImage = this.currentImages()[0] ?? null;
                },
                current() {
                    if (this.unavailable) {
                        return null;
                    }

                    return this.variants.find(v => v.id === this.currentVariantId) ?? this.variants[0] ?? null;
                },
                updateVariant() {
                    const match = this.variants.find(v =>
                        this.dimensions.every(d => !this.selected[d.code] || v.dims[d.code] === this.selected[d.code])
                    );

                    if (match) {
                        this.unavailable = false;
                        this.currentVariantId = match.id;
                        this.activeImage = this.currentImages()[0] ?? null;
                    } else {
                        this.unavailable = true;
                        this.currentVariantId = null;
                        this.activeImage = this.productImages[0] ?? null;
                    }
                },
                dimensionOptions(code) {
                    return [...new Set(this.variants.map(v => v.dims[code]).filter(v => v !== undefined && v !== null))];
                },
                currentImages() {
                    const variant = this.current();
                    if (! variant) return this.productImages;
                    const variantImages = variant.images && variant.images.length ? variant.images : [];
                    return [...new Set([...variantImages, ...this.productImages])];
                },
                discountPercent() {
                    const v = this.current();
                    if (! v) return null;
                    if (!v.compare_at_price || v.compare_at_price <= v.price) return null;
                    return Math.round(((v.compare_at_price - v.price) / v.compare_at_price) * 100);
                },
                availabilityLabel() {
                    const v = this.current();
                    if (! v) return '';
                    if (v.fulfillment_strategy === 'preorder') return 'Pre-Order';
                    if (v.fulfillment_strategy === 'dropship') return 'Available';
                    return {
                        in_stock: 'In Stock',
                        out_of_stock: 'Out of Stock',
                        discontinued: 'Discontinued'
                    } [v.availability] ?? '';
                },
                formatPrice(cents) {
                    return '৳' + Math.round(cents / 100).toLocaleString();
                },
                toast(message, type = 'success') {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            message,
                            type
                        }
                    }));
                },
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').content;
                },
                addToCart() {
                    if (! this.current()) return;
                    this.cartLoading = true;
                    fetch('{{ route('storefront.cart.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                product_variant_id: this.currentVariantId,
                                quantity: this.quantity
                            }),
                        })
                        .then(r => {
                            if (!r.ok) throw new Error();
                            return r;
                        })
                        .then(() => {
                            this.toast('Added to cart');
                            if (window.Livewire) window.Livewire.dispatch('cart-updated');
                        })
                        .catch(() => this.toast('Could not add to cart — please try again', 'error'))
                        .finally(() => this.cartLoading = false);
                },
                toggleWishlist() {
                    this.wishlistLoading = true;
                    fetch('{{ route('storefront.wishlist.toggle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                product_id: {{ $product->id }}
                            }),
                        })
                        .then(r => {
                            if (!r.ok) throw new Error();
                            this.wishlisted = !this.wishlisted;
                            this.toast(this.wishlisted ? 'Added to wishlist' : 'Removed from wishlist');
                        })
                        .catch(() => this.toast('Something went wrong', 'error'))
                        .finally(() => this.wishlistLoading = false);
                },
                toggleCompare() {
                    this.compareLoading = true;
                    fetch('{{ route('storefront.compare.toggle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                product_id: {{ $product->id }}
                            }),
                        })
                        .then(r => {
                            if (!r.ok) {
                                return r.json().then(d => { throw new Error(d.message || 'Something went wrong'); });
                            }
                            return r.json();
                        })
                        .then(d => {
                            this.comparing = !!d.added;
                            this.toast(d.message);
                            if (window.Livewire) window.Livewire.dispatch('compare-updated');
                        })
                        .catch(err => this.toast(err.message || 'Something went wrong', 'error'))
                        .finally(() => this.compareLoading = false);
                },
            }
        }
    </script>
@endpush