@extends('storefront.layout')

@section('title', ($product->translation('en')?->name ?? 'Product') . ' - ' . tenant()->name)

@section('content')
    @php
        $productDescription = $product->translation('en')?->sanitizedDescription();
        $showSpecifications = $specificationGroups->isNotEmpty();
        $showDescription = filled($productDescription);
        $showWarranty = filled($product->translation('en')?->warranty_info);
        $showReviews = $product->reviews_count > 0;
        $showFaqs = $product->faqs->isNotEmpty();
        $navSections = collect([
            'specifications' => $showSpecifications,
            'description' => $showDescription,
            'warranty' => $showWarranty,
            'reviews' => $showReviews,
            'faq' => $showFaqs,
        ])
            ->filter()
            ->keys()
            ->values();
        $navLabels = [
            'specifications' => 'Specifications',
            'description' => 'Description',
            'warranty' => 'Warranty',
            'reviews' => 'Reviews',
            'faq' => 'FAQ',
        ];
        $policyLinks = collect($policyLinks ?? []);
        $warrantyPolicyLink = $policyLinks->first(fn($link) => $link['label'] === 'Warranty');
        $canonicalProductUrl = app(\App\Support\Tenancy\TenantUrlGenerator::class)
            ->canonicalRoute(tenant(), 'storefront.product', [$product->translation('en')?->slug]);

        // Server-rendered EMI figures (progressive enhancement baseline). Uses
        // the first variant's price — the same variant Alpine starts on — and
        // mirrors the client formula exactly: round(price * (1 + rate/100) / tenure).
        $emiBasePrice = $product->variants->first()?->price ?? 0;
        $emiFromMonthly = $product->emiPlans->isNotEmpty()
            ? $product->emiPlans
                ->map(
                    fn($plan) => round(
                        ($emiBasePrice * (1 + (float) $plan->interest_rate / 100)) / $plan->tenure_months,
                    ),
                )
                ->min()
            : null;
        $emiHasZero = $product->emiPlans->contains(fn($plan) => (float) $plan->interest_rate === 0.0);
    @endphp

    @include('storefront.partials.seo-meta', [
        'description' => $product->translation('en')?->meta_description,
        'canonical' => $canonicalProductUrl,
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

            .pdp-sticky-cta {
                transition: transform .25s ease, opacity .25s ease;
            }

            @media (prefers-reduced-motion: reduce) {
                html {
                    scroll-behavior: auto;
                }

                .pdp-sticky-cta {
                    transition: none;
                }
            }
        </style>
    @endpush

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-24 lg:pb-8" x-data="productDetail(@js($variantsData), @js($productImages), @js($dimensions), @js($initialVariantId), @js($isWishlisted), @js($isComparing), @js($emiData), @js($requiresSelection))" x-init="init()">
        <nav class="text-sm text-gray-500 mb-6" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            @if ($product->category)
                <span class="mx-1">/</span>
                <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.category', [$product->category->slug]) }}"
                    class="hover:text-[var(--brand)]">{{ $product->category->name }}</a>
            @endif
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $product->translation('en')?->name }}</span>
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            {{-- Gallery --}}
            <div>
                <button type="button" @click="lightboxOpen = true" :disabled="!hasUsableImage()"
                    class="block w-full aspect-square rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-900 relative group cursor-zoom-in disabled:cursor-default"
                    aria-label="Open full-size image">
                    {{-- Driven purely by currentImages().length, never by activeImage — so this
                         can never go blank even if activeImage momentarily desyncs. --}}
                    <div x-show="currentImages().length === 0" x-cloak
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-600">
                        <x-ui.icon name="image" class="w-16 h-16" />
                        <span class="text-sm font-medium text-gray-400 dark:text-gray-500">No image available</span>
                    </div>

                    <template x-for="image in currentImages()" :key="image.src">
                        <img x-show="image.src === resolvedActiveImage() && !erroredImages[image.src]"
                            x-transition:enter="transition-opacity duration-200 ease-out"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" :src="image.src"
                            :alt="image.alt" width="600" height="600" loading="eager" x-init="$el.complete && $el.naturalWidth > 0 && markLoaded(image.src)"
                            x-on:load="markLoaded(image.src)" x-on:error="markErrored(image.src)"
                            class="w-full h-full object-cover">
                    </template>

                    <div x-show="currentImages().length > 0 && !isLoaded(resolvedActiveImage()) && !erroredImages[resolvedActiveImage()]"
                        x-cloak class="absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-900">
                        <div class="h-9 w-9 rounded-full border-2 border-gray-300 dark:border-gray-700 border-t-[var(--brand)] animate-spin"
                            aria-hidden="true"></div>
                    </div>

                    <div x-show="currentImages().length > 0 && erroredImages[resolvedActiveImage()]" x-cloak
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-600">
                        <x-ui.icon name="image" class="w-16 h-16" />
                        <span class="text-sm font-medium text-gray-400 dark:text-gray-500">Image unavailable</span>
                    </div>

                    <span x-show="hasUsableImage()"
                        class="absolute bottom-3 right-3 p-2 rounded-full bg-white/90 dark:bg-gray-900/90 shadow-soft opacity-0 group-hover:opacity-100 transition">
                        <x-ui.icon name="search" class="w-4 h-4" />
                    </span>
                </button>
                <div class="flex gap-2 mt-4 overflow-x-auto pb-1">
                    <template x-for="image in currentImages()" :key="'thumb-' + image.src">
                        <button @click="activeImage = image.src"
                            class="w-16 h-16 rounded-lg overflow-hidden border-2 flex-shrink-0 transition"
                            :class="resolvedActiveImage() === image.src ? 'border-[var(--brand)]' : 'border-transparent'">
                            <img :src="image.src" :alt="image.alt" width="64" height="64" loading="lazy"
                                class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Lightbox --}}
            <template x-teleport="body">
                <div x-show="lightboxOpen" x-cloak
                    class="fixed inset-0 z-[70] bg-black/90 flex items-center justify-center p-4 sm:p-10" role="dialog"
                    aria-modal="true" aria-label="Product image" @keydown.escape.window="lightboxOpen = false"
                    @click="lightboxOpen = false">
                    <button type="button" @click.stop="lightboxOpen = false"
                        class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition"
                        aria-label="Close">
                        <x-ui.icon name="close" class="w-6 h-6" />
                    </button>
                    <template x-for="image in currentImages()" :key="'lightbox-' + image.src">
                        <img x-show="image.src === resolvedActiveImage()" :src="image.src" :alt="image.alt"
                            @click.stop class="max-w-full max-h-full object-contain rounded-lg">
                    </template>
                    <template x-if="currentImages().length > 1">
                        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2" @click.stop>
                            <template x-for="image in currentImages()" :key="'lightbox-dot-' + image.src">
                                <button @click="activeImage = image.src" class="w-2 h-2 rounded-full transition"
                                    :class="resolvedActiveImage() === image.src ? 'bg-white' : 'bg-white/40'"
                                    aria-label="Show image"></button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Details --}}
            <div x-ref="buyBox">
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
                        <button @click="$store.wishlist.toggle({{ $product->id }})"
                            :disabled="$store.wishlist.pending[{{ $product->id }}]"
                            :aria-busy="$store.wishlist.pending[{{ $product->id }}]"
                            class="p-2.5 rounded-xl border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                            :class="$store.wishlist.isWishlisted({{ $product->id }}) ?
                                'border-red-300 bg-red-50 dark:bg-red-950 text-red-600' :
                                'border-gray-300 dark:border-gray-700'"
                            :aria-pressed="$store.wishlist.isWishlisted({{ $product->id }})"
                            :aria-label="$store.wishlist.isWishlisted({{ $product->id }}) ?
                                'Remove {{ $product->translation('en')?->name }} from wishlist' :
                                'Add {{ $product->translation('en')?->name }} to wishlist'">
                            <span x-show="!$store.wishlist.isWishlisted({{ $product->id }})"><x-ui.icon name="heart"
                                    class="w-5 h-5" /></span>
                            <span x-show="$store.wishlist.isWishlisted({{ $product->id }})" x-cloak><x-ui.icon
                                    name="heart" :solid="true" class="w-5 h-5 text-red-600" /></span>
                        </button>
                        <button @click="toggleCompare()" :disabled="compareLoading"
                            class="p-2.5 rounded-xl border transition text-sm"
                            :class="comparing ? 'border-[var(--brand)] bg-[var(--brand)]/10 text-[var(--brand)]' :
                                'border-gray-300 dark:border-gray-700'"
                            aria-label="Toggle compare" :aria-pressed="comparing">
                            <x-ui.icon name="grid" class="w-5 h-5" />
                        </button>
                        <button @click="share()" :disabled="shareLoading"
                            class="p-2.5 rounded-xl border transition text-sm border-gray-300 dark:border-gray-700"
                            aria-label="Share product">
                            <x-ui.icon name="share" class="w-5 h-5" x-show="!shareLoading" />
                            <svg x-show="shareLoading" class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <template x-if="selectionIssueType()">
                    <div class="mt-4 rounded-xl border p-3 text-sm"
                        :class="selectionIssueType() === 'invalid' ?
                            'border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300' :
                            'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300'">
                        <span x-text="selectionMessage()"></span>
                    </div>
                </template>

                <template x-if="current()">
                    <div>
                        <div class="mt-4 flex items-baseline gap-2 flex-wrap">
                            <span class="font-bold text-3xl" x-text="formatPrice(current().price)"></span>
                            <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                                <span class="text-gray-400 line-through text-sm"
                                    x-text="formatPrice(current().compare_at_price)"></span>
                            </template>
                            <template x-if="discountPercent()">
                                <x-ui.badge variant="danger"><span
                                        x-text="discountPercent() + '% OFF'"></span></x-ui.badge>
                            </template>
                        </div>

                        <p class="text-sm mt-2 font-medium" :class="availabilityTone()" x-text="availabilityLabel()"></p>
                        <template x-if="current().purchase_state === 'low_stock' && current().available_quantity > 0">
                            <p class="text-sm mt-0.5 text-amber-600 font-medium"
                                x-text="'Only ' + current().available_quantity + ' left in stock'"></p>
                        </template>
                        <template x-if="restockMessage()">
                            <p class="text-sm mt-0.5 text-gray-500" x-text="restockMessage()"></p>
                        </template>
                    </div>
                </template>

                <template x-for="dimension in dimensions" :key="dimension.code">
                    <div class="mt-4">
                        <p class="text-sm font-medium mb-2" x-text="dimension.label"></p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="option in dimensionOptions(dimension.code)"
                                :key="dimension.code + '-' + option">
                                <button @click="selected[dimension.code] = option; updateVariant()"
                                    :aria-pressed="selected[dimension.code] === option"
                                    class="px-3 py-1.5 rounded-full border text-sm transition"
                                    :class="selected[dimension.code] === option ?
                                        'border-[var(--brand)] text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' :
                                        'border-gray-300 dark:border-gray-700 hover:border-gray-400'"
                                    x-text="option + (dimension.suffix || '')"></button>
                            </template>
                        </div>
                    </div>
                </template>

                @if ($product->emiPlans->isNotEmpty())
                    <div
                        class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-800 p-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
                                <span class="text-gray-900 dark:text-gray-100">EMI from</span>
                                <span class="font-bold text-gray-900 dark:text-gray-100 tabular-nums"
                                    x-text="emiHeadline()">৳{{ number_format(($emiFromMonthly ?? 0) / 100) }}/month</span>
                            </p>
                            @if ($emiHasZero)
                                <p class="mt-1 text-xs text-green-600 dark:text-green-400 font-medium">0% EMI available</p>
                            @endif
                        </div>
                        <button type="button" @click="openEmi()" aria-haspopup="dialog"
                            class="flex-shrink-0 text-sm font-medium text-[var(--brand)] hover:underline underline-offset-4">
                            View plans
                        </button>
                    </div>

                    {{-- EMI modal --}}
                    <template x-teleport="body">
                        <div x-show="emiOpen" x-cloak
                            class="fixed inset-0 z-[80] bg-black/60 flex items-center justify-center p-4 sm:p-6"
                            role="dialog" aria-modal="true" aria-labelledby="emi-modal-title"
                            @keydown.escape.window="closeEmi()" @click="closeEmi()">
                            <div class="relative w-full max-w-lg max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden"
                                x-ref="emiPanel" @click.stop>
                                <header
                                    class="flex items-start justify-between gap-4 border-b border-gray-200 dark:border-gray-800 p-5">
                                    <div>
                                        <h2 id="emi-modal-title"
                                            class="text-lg font-bold text-gray-900 dark:text-gray-100">EMI Plans</h2>
                                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                            <span
                                                x-text="emiHeadline()">৳{{ number_format(($emiFromMonthly ?? 0) / 100) }}/month</span>
                                        </p>
                                    </div>
                                    <button type="button" x-ref="emiClose" @click="closeEmi()"
                                        class="p-2 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                                        aria-label="Close EMI plans">
                                        <x-ui.icon name="close" class="w-5 h-5" />
                                    </button>
                                </header>

                                <div class="overflow-y-auto p-5 space-y-3">
                                    @foreach ($product->emiPlans as $plan)
                                        @php $planRate = (float) $plan->interest_rate; @endphp
                                        <div
                                            class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 dark:border-gray-800 p-4">
                                            <div class="min-w-0">
                                                <p
                                                    class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $plan->bank_name }}
                                                    @if ($planRate === 0.0)
                                                        <x-ui.badge variant="success">0% EMI</x-ui.badge>
                                                    @endif
                                                </p>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    <span class="tabular-nums"
                                                        x-text="formatPrice(emiMonthly((current()?.price ?? 0), {{ $planRate }}, {{ $plan->tenure_months }}))">৳{{ number_format(round(($emiBasePrice * (1 + $planRate / 100)) / $plan->tenure_months) / 100) }}</span>/month
                                                    for {{ $plan->tenure_months }} months
                                                </p>
                                            </div>
                                            <div class="text-right flex-shrink-0">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $planRate }}%
                                                    interest</p>
                                                <p
                                                    class="text-sm font-medium text-gray-900 dark:text-gray-100 tabular-nums">
                                                    <span
                                                        x-text="formatPrice(emiMonthly((current()?.price ?? 0), {{ $planRate }}, {{ $plan->tenure_months }}) * {{ $plan->tenure_months }})">৳{{ number_format((round(($emiBasePrice * (1 + $planRate / 100)) / $plan->tenure_months) * $plan->tenure_months) / 100) }}</span>
                                                    total
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
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
                    <form method="POST" action="{{ route('storefront.buy-now') }}" class="flex-1"
                        x-data="{ pending: false }"
                        @submit="if (!current() || !current().purchasable) { $event.preventDefault(); return; } pending = true">
                        @csrf
                        <input type="hidden" name="product_variant_id" :value="currentVariantId || ''">
                        <input type="hidden" name="quantity" :value="quantity">
                        <x-ui.button variant="primary" size="lg" class="w-full" type="submit"
                            x-bind:disabled="pending || cartLoading || !current() || !current().purchasable">
                            <span x-text="current() && current().purchase_state === 'preorder' ? 'Pre-Order Now' : 'Buy Now'"></span>
                        </x-ui.button>
                    </form>
                </div>
                <div class="mt-3">
                    <x-ui.button variant="secondary" size="lg" class="w-full" @click="addToCart()"
                        x-bind:disabled="cartLoading || !current() || !current().purchasable">
                        <span x-text="ctaLabel()"></span>
                    </x-ui.button>
                </div>
            </div>
        </div>

        {{-- Sticky mobile purchase bar — sits above the persistent mobile bottom nav
             (bottom-16 ≈ its height) so it never overlaps it; the bottom nav keeps
             the --safe-bottom padding as the bottommost fixed element. It appears
             only after the main buy box leaves the viewport (IntersectionObserver,
             see setupStickyCta in productDetail) and retracts near the end of the
             page so it never covers the footer. Motion respects reduced-motion via
             the .pdp-sticky-cta transition rule. --}}
        <template x-if="showSticky()">
            <div class="pdp-sticky-cta lg:hidden fixed bottom-16 inset-x-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 px-4 py-3"
                :class="stickyCtaVisible ? 'translate-y-0 opacity-100 shadow-soft' :
                    'translate-y-full opacity-0 pointer-events-none'"
                :aria-hidden="stickyCtaVisible ? 'false' : 'true'">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-shrink-0">
                        <template x-if="current()">
                            <div>
                                <p class="font-bold text-lg leading-none" x-text="formatPrice(current().price)"></p>
                                <template
                                    x-if="current().compare_at_price && current().compare_at_price > current().price">
                                    <p class="text-xs text-gray-400 line-through leading-none mt-1"
                                        x-text="formatPrice(current().compare_at_price)"></p>
                                </template>
                            </div>
                        </template>
                    </div>
                    <form method="POST" action="{{ route('storefront.buy-now') }}" class="flex-1"
                        x-data="{ pending: false }"
                        @submit="if (!current() || !current().purchasable) { $event.preventDefault(); return; } pending = true">
                        @csrf
                        <input type="hidden" name="product_variant_id" :value="currentVariantId || ''">
                        <input type="hidden" name="quantity" :value="quantity">
                        <x-ui.button variant="primary" size="lg" class="w-full" type="submit"
                            x-bind:disabled="pending || cartLoading || !current() || !current().purchasable">
                            <span x-text="current() && current().purchase_state === 'preorder' ? 'Pre-Order Now' : 'Buy Now'"></span>
                        </x-ui.button>
                    </form>
                </div>
                <div class="mt-2">
                    <x-ui.button variant="secondary" size="lg" class="w-full" @click="addToCart()"
                        x-bind:disabled="cartLoading || !current() || !current().purchasable">
                        <span x-text="ctaLabel()"></span>
                    </x-ui.button>
                </div>
                <template x-if="!current()">
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-400 font-medium" x-text="selectionMessage()">
                    </p>
                </template>
            </div>
        </template>

        @if ($policyLinks->isNotEmpty())
            <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-800">
                <ul aria-label="Store policies" class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ($policyLinks as $link)
                        <li>
                            <a href="{{ route('storefront.page', $link['slug']) }}"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-[var(--brand)]">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($shippingMethods->isNotEmpty() || $paymentMethods->isNotEmpty())
            <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-800"
                aria-label="Delivery and payment information">
                <ul class="space-y-2.5">
                    @if ($shippingMethods->isNotEmpty())
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex-shrink-0 text-gray-400">
                                <x-ui.icon name="truck" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0 text-sm">
                                <p class="font-medium">Delivery</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-gray-500 dark:text-gray-400">
                                    @foreach ($shippingMethods as $method)
                                        <li class="flex items-center gap-1.5">
                                            {{ $method->name }}
                                            @if ($method->type === \App\Enums\ShippingMethodType::Free || $method->cost === 0)
                                                <span class="text-green-600 dark:text-green-400 font-medium">Free</span>
                                            @else
                                                <span
                                                    class="tabular-nums">৳{{ number_format($method->cost / 100) }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endif
                    @if ($paymentMethods->isNotEmpty())
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex-shrink-0 text-gray-400">
                                <x-ui.icon name="card" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0 text-sm">
                                <p class="font-medium">Payment</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-gray-500 dark:text-gray-400">
                                    @foreach ($paymentMethods as $method)
                                        <li class="flex items-center gap-1.5">{{ $method->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($navSections->isNotEmpty())
            <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections"
                class="sticky top-16 lg:top-28 z-30 -mx-4 sm:mx-0 mt-8 lg:mt-12 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
                <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
                    @foreach ($navSections as $sectionId)
                        <a href="#{{ $sectionId }}"
                            class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10"
                            :class="isActive('{{ $sectionId }}') ?
                                'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : ''"
                            :aria-current="isActive('{{ $sectionId }}') ? 'location' : null">
                            {{ $navLabels[$sectionId] ?? ucfirst($sectionId) }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif

        {{-- Specifications --}}
        <section id="specifications" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
            aria-labelledby="specifications-heading">
            <h2 id="specifications-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Specifications</h2>
            <div class="mt-5">
                @forelse($specificationGroups as $group)
                    <div class="mb-8 last:mb-0">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                            {{ $group['group'] }}
                        </h3>
                        <dl
                            class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-100 dark:border-gray-800">
                            @foreach ($group['items'] as $value)
                                <div class="flex py-2.5 text-sm gap-4 px-4">
                                    <dt class="w-1/3 flex-shrink-0 text-gray-500">{{ $value->attributeDefinition->label }}
                                    </dt>
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
            <section id="description" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="description-heading">
                <h2 id="description-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Description</h2>
                <div class="prose dark:prose-invert max-w-none mt-5">
                    {!! $productDescription !!}
                </div>
            </section>
        @endif

        {{-- Warranty --}}
        @if ($showWarranty)
            <section id="warranty" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="warranty-heading">
                <h2 id="warranty-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Warranty</h2>
                <div class="prose dark:prose-invert max-w-none mt-5">
                    {!! nl2br(e($product->translation('en')->warranty_info)) !!}
                </div>
                @if ($warrantyPolicyLink)
                    <div class="mt-5">
                        <a href="{{ route('storefront.page', $warrantyPolicyLink['slug']) }}"
                            class="text-sm font-medium text-[var(--brand)] hover:underline">
                            View Warranty Policy &rarr;
                        </a>
                    </div>
                @endif
            </section>
        @endif

        {{-- Reviews --}}
        <section id="reviews" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
            aria-labelledby="reviews-heading">
            <h2 id="reviews-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Reviews
                ({{ $product->reviews_count }})</h2>

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
            <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="faq-heading">
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
        @if ($relatedCards->isNotEmpty())
            <section id="related" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="related-heading">
                <h2 id="related-heading" class="text-xl lg:text-2xl font-bold tracking-tight">You May Also Like</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($relatedCards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Merchandising rails: cross-sell, upsell, frequently-bought and
             compatible-accessory relations, each only when populated. --}}
        @if ($crossSellCards->isNotEmpty())
            <section id="cross-sells" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="cross-sells-heading">
                <h2 id="cross-sells-heading" class="text-xl lg:text-2xl font-bold tracking-tight">You May Also Like</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($crossSellCards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($upsellCards->isNotEmpty())
            <section id="upsells" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="upsells-heading">
                <h2 id="upsells-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Upgrade Your Choice</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($upsellCards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($frequentlyBoughtCards->isNotEmpty())
            <section id="frequently-bought-together" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="frequently-bought-heading">
                <h2 id="frequently-bought-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Frequently Bought
                    Together</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($frequentlyBoughtCards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($compatibleAccessoryCards->isNotEmpty())
            <section id="compatible-accessories" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="compatible-accessories-heading">
                <h2 id="compatible-accessories-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Compatible
                    Accessories</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
                    @foreach ($compatibleAccessoryCards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Recently viewed (reuses the existing RecentlyViewedService pipeline;
             ordering is the service's most-recent-first, preserved after fetch). --}}
        @if ($recentlyViewedCards->isNotEmpty())
            <section id="recently-viewed" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16"
                aria-labelledby="recently-viewed-heading">
                <h2 id="recently-viewed-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Recently Viewed</h2>
                <div class="mt-5 -mx-4 sm:mx-0">
                    <div class="flex gap-4 overflow-x-auto px-4 sm:px-0 pb-2 snap-x">
                        @foreach ($recentlyViewedCards as $card)
                            <div class="w-44 flex-shrink-0 snap-start">
                                @include('storefront.partials.product-card', ['card' => $card])
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Sentinel for the sticky CTA: once the end of the product content
             scrolls into view, the bar retracts so it never covers the footer. --}}
        <div x-ref="pdpEnd" class="h-px" aria-hidden="true"></div>
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
                    }, {
                        rootMargin: '-45% 0px -50% 0px'
                    });

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

        function productDetail(variants, productImages, dimensions, initialId, initialWishlisted, initialComparing,
            emiPlans, requiresSelection) {
            return {
                variants,
                productImages,
                dimensions,
                requiresSelection,
                selected: {},
                activeImage: null,
                loadedImages: {},
                erroredImages: {},
                lightboxOpen: false,
                currentVariantId: initialId,
                unavailable: false,
                quantity: 1,
                comparing: initialComparing,
                cartLoading: false,
                compareLoading: false,
                shareLoading: false,
                stickyCtaVisible: false,
                buyBoxObserver: null,
                endObserver: null,
                emiPlans,
                emiOpen: false,
                emiTrigger: null,

                init() {
                    this.$store.wishlist.seed({{ $product->id }}, initialWishlisted);

                    if (this.requiresSelection) {
                        // Multi-option product: never auto-select a variant. The
                        // shopper must explicitly pick every dimension before any
                        // purchase action resolves.
                        this.currentVariantId = null;
                    } else {
                        const first = this.current();

                        if (first) {
                            dimensions.forEach(d => {
                                if (first.dims[d.code] !== undefined && first.dims[d.code] !== null) {
                                    this.selected[d.code] = first.dims[d.code];
                                }
                            });
                        }
                    }

                    this.activeImage = this.currentImages()[0]?.src ?? null;
                    this.setupStickyCta();
                },
                setupStickyCta() {
                    if (!('IntersectionObserver' in window)) return;

                    const buyBox = this.$refs.buyBox;
                    if (!buyBox) return;

                    // Show the sticky bar once the main purchase area has left
                    // the viewport (a little past the bottom so it does not
                    // flash while the user is still reading the buy box).
                    this.buyBoxObserver = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            this.stickyCtaVisible = !entry.isIntersecting;
                        });
                    }, {
                        rootMargin: '0px 0px -15% 0px'
                    });
                    this.buyBoxObserver.observe(buyBox);

                    // Retract near the end of the product content so the bar
                    // never sits on top of the footer / related products.
                    const end = this.$refs.pdpEnd;
                    if (end) {
                        this.endObserver = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) {
                                    this.stickyCtaVisible = false;
                                }
                            });
                        }, {
                            rootMargin: '0px 0px -15% 0px'
                        });
                        this.endObserver.observe(end);
                    }
                },
                destroy() {
                    this.buyBoxObserver?.disconnect();
                    this.endObserver?.disconnect();
                },
                activeVariants() {
                    return this.variants.filter(v => v.is_active);
                },
                missingDimensions() {
                    if (!this.requiresSelection) return [];
                    return this.dimensions.filter(d => this.selected[d.code] === undefined || this.selected[d.code] ===
                        null);
                },
                selectionComplete() {
                    return this.missingDimensions().length === 0;
                },
                // 'incomplete': shopper hasn't finished picking every dimension yet — a
                //   normal, expected mid-flow state (neutral tone).
                // 'invalid': every dimension is picked but no active variant matches
                //   that exact combination — a real dead end (warning tone).
                // null: either a concrete variant resolved, or this product doesn't
                //   require selection at all.
                selectionIssueType() {
                    if (!this.requiresSelection) return null;
                    if (!this.selectionComplete()) return 'incomplete';
                    if (!this.current()) return 'invalid';
                    return null;
                },
                selectionMessage() {
                    if (this.requiresSelection && !this.selectionComplete()) {
                        const missing = this.missingDimensions().map(d => d.label);
                        if (missing.length === 0) return 'Please select all product options';
                        return 'Please select ' + missing.join(' and ');
                    }
                    return 'This combination of options is not available.';
                },
                showSticky() {
                    return this.current() !== null || (this.requiresSelection && !this.selectionComplete());
                },
                current() {
                    if (this.unavailable) {
                        return null;
                    }

                    if (this.requiresSelection) {
                        if (!this.selectionComplete()) return null;

                        const matches = this.activeVariants().filter(v =>
                            this.dimensions.every(d => v.dims[d.code] === this.selected[d.code])
                        );

                        return matches.length === 1 ? matches[0] : null;
                    }

                    return this.activeVariants().find(v => v.id === this.currentVariantId) ?? null;
                },
                updateVariant() {
                    const match = this.current();

                    if (match) {
                        this.unavailable = false;
                        this.currentVariantId = match.id;
                    } else if (this.requiresSelection && !this.selectionComplete()) {
                        // Still picking options — currentImages() falls back to
                        // the product/preview gallery until a concrete variant
                        // resolves (see currentImages()).
                        this.unavailable = false;
                        this.currentVariantId = null;
                    } else {
                        this.unavailable = true;
                        this.currentVariantId = null;
                    }

                    // Single source of truth for the fallback chain lives in
                    // currentImages(); just point activeImage at its first
                    // result here rather than duplicating the fallback logic.
                    this.activeImage = this.currentImages()[0]?.src ?? null;
                },
                dimensionOptions(code) {
                    return [...new Set(this.activeVariants().map(v => v.dims[code]).filter(v => v !== undefined && v !==
                        null))];
                },
                currentImages() {
                    const variant = this.current();

                    if (variant) {
                        const variantImages = variant.images && variant.images.length ? variant.images : [];
                        return this.dedupeImages([...variantImages, ...this.productImages]);
                    }

                    // No concrete variant resolved yet (nothing picked, still
                    // incomplete, or an invalid combination). Show product
                    // images if there are any; if the product itself has no
                    // photos, fall back to the first active variant that does
                    // have photos so the shopper sees a real preview instead
                    // of an empty gallery before they've finished choosing.
                    if (this.productImages.length > 0) {
                        return this.productImages;
                    }

                    const preview = this.activeVariants().find(v => v.images && v.images.length);

                    return preview ? this.dedupeImages(preview.images) : [];
                },
                dedupeImages(images) {
                    const seen = new Set();
                    return images.filter(img => {
                        if (seen.has(img.src)) return false;
                        seen.add(img.src);
                        return true;
                    });
                },
                hasUsableImage() {
                    return this.currentImages().length > 0;
                },
                // Self-healing: always returns a src that's actually in the current
                // image set. If activeImage is null, stale, or points at an image
                // that's no longer in currentImages() (e.g. right after switching
                // selections), this falls back to the first available image instead
                // of leaving every x-show comparison false and the gallery blank.
                resolvedActiveImage() {
                    const images = this.currentImages();
                    if (images.length === 0) return null;
                    if (images.some(img => img.src === this.activeImage)) return this.activeImage;
                    return images[0].src;
                },
                markLoaded(src) {
                    if (src) this.loadedImages[src] = true;
                },
                markErrored(src) {
                    if (!src) return;
                    this.erroredImages[src] = true;
                    this.loadedImages[src] = true;
                },
                isLoaded(src) {
                    return !!this.loadedImages[src];
                },
                discountPercent() {
                    const v = this.current();
                    if (!v) return null;
                    if (!v.compare_at_price || v.compare_at_price <= v.price) return null;
                    return Math.round(((v.compare_at_price - v.price) / v.compare_at_price) * 100);
                },
                availabilityLabel() {
                    const v = this.current();
                    if (!v) return '';
                    if (v.purchase_state === 'preorder') return 'Pre-Order';
                    if (v.purchase_state === 'dropship') return 'Available';
                    if (v.purchase_state === 'discontinued') return 'Discontinued';
                    if (v.purchase_state === 'out_of_stock') return v.backorder_policy ? 'Backorder' : 'Out of Stock';
                    return 'In Stock';
                },
                availabilityTone() {
                    const v = this.current();
                    if (!v) return 'text-gray-500';
                    const state = v.purchase_state;
                    if (state === 'discontinued' || state === 'out_of_stock') return 'text-red-500';
                    if (state === 'preorder') return 'text-purple-600';
                    if (state === 'low_stock') return 'text-amber-600';
                    return 'text-green-600';
                },
                restockMessage() {
                    const v = this.current();
                    if (!v || !v.expected_available_at) return '';
                    if (v.purchase_state === 'preorder') return 'Expected availability ' + v.expected_available_at;
                    if (v.purchase_state === 'out_of_stock' && !v.backorder_policy) return 'Back in stock ' + v
                        .expected_available_at;
                    return '';
                },
                ctaLabel() {
                    const v = this.current();
                    if (this.cartLoading) return 'Adding…';
                    if (!v) return 'Unavailable';
                    if (!v.purchasable) {
                        return v.purchase_state === 'discontinued' ? 'Discontinued' : 'Out of Stock';
                    }
                    if (v.purchase_state === 'preorder') return 'Pre-Order Now';
                    if (v.purchase_state === 'out_of_stock') {
                        return v.backorder_policy === 'notify' ? 'Backorder Now' : 'Add to Cart';
                    }
                    return 'Add to Cart';
                },
                formatPrice(cents) {
                    return '৳' + Math.round(cents / 100).toLocaleString();
                },
                // Mirrors the original PDP formula exactly:
                // round(price * (1 + rate/100) / tenure) in cents.
                emiMonthly(price, rate, tenure) {
                    return Math.round(price * (1 + rate / 100) / tenure);
                },
                emiFrom() {
                    const v = this.current();
                    if (!v || !this.emiPlans.length) return null;
                    return Math.min(...this.emiPlans.map(p => this.emiMonthly(v.price, p.rate, p.tenure)));
                },
                emiHeadline() {
                    const from = this.emiFrom();
                    return from === null ? '' : this.formatPrice(from) + '/month';
                },
                openEmi() {
                    this.emiTrigger = document.activeElement;
                    this.emiOpen = true;
                    document.body.style.overflow = 'hidden';
                    this.$nextTick(() => this.$refs.emiClose?.focus());
                },
                closeEmi() {
                    this.emiOpen = false;
                    document.body.style.overflow = '';
                    if (this.emiTrigger) {
                        this.$nextTick(() => this.emiTrigger?.focus?.());
                        this.emiTrigger = null;
                    }
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
                    const v = this.current();
                    if (!v || !v.purchasable) return;
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
                                return r.json().then(d => {
                                    throw new Error(d.message || 'Something went wrong');
                                });
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
                share() {
                    const url = document.querySelector('link[rel="canonical"]')?.href ?? window.location.href;
                    const data = {
                        title: document.title,
                        text: 'Check out this product',
                        url,
                    };

                    this.shareLoading = true;

                    const copyLink = async () => {
                        try {
                            await navigator.clipboard.writeText(url);
                            this.toast('Link copied to clipboard');
                        } catch {
                            // Legacy fallback when the Clipboard API is unavailable.
                            const textarea = document.createElement('textarea');
                            textarea.value = url;
                            textarea.style.position = 'fixed';
                            textarea.style.opacity = '0';
                            document.body.appendChild(textarea);
                            textarea.select();
                            try {
                                document.execCommand('copy');
                                this.toast('Link copied to clipboard');
                            } catch {
                                this.toast('Could not copy the link', 'error');
                            } finally {
                                document.body.removeChild(textarea);
                            }
                        }
                    };

                    const finish = () => this.shareLoading = false;

                    if (navigator.share) {
                        navigator.share(data).then(finish).catch((err) => {
                            if (err && err.name === 'AbortError') {
                                finish();

                                return;
                            }
                            copyLink().then(finish);
                        });

                        return;
                    }

                    copyLink().then(finish);
                },
            }
        }
    </script>
@endpush
