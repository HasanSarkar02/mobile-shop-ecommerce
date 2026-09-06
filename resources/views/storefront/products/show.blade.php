@extends('storefront.layout')

@section('title', $seo->title)

@section('content')
    @php
        $translation = $product->translation() ?? $product->translation('en');
        $productDescription = optional($translation)->sanitizedDescription();
        $showSpecifications = $specificationGroups->isNotEmpty();
        $showDescription = filled($productDescription);
        $showWarranty = filled(optional($translation)->warranty_info);
        // Industry-specific information priority (IndustryConfig::pdp.information_priority)
        // Reorders navSections according to vertical preset, unknown industries fall back to standard.
        $informationPriority = \App\Support\IndustryConfig::currentGet('pdp.information_priority', ['specifications','description','warranty','reviews','faq']);
        if (! is_array($informationPriority)) {
            $informationPriority = ['specifications','description','warranty','reviews','faq'];
        }
        // Reviews must always be reachable (empty state when 0) — see Task 4.
        $showReviews = true;
        // FAQ: respect IndustryConfig — render section (with empty state) only when vertical expects FAQ.
        $faqConfigured = in_array('faq', $informationPriority, true);
        $showFaqs = $faqConfigured;
        $hasFaqs = $product->faqs->isNotEmpty();
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
        if ($informationPriority !== []) {
            $priorityIndex = array_flip($informationPriority);
            $navSections = $navSections->sortBy(fn ($section) => $priorityIndex[$section] ?? 999)->values();
        }
        $navLabels = [
            'specifications' => __('Specifications'),
            'description' => __('Description'),
            'warranty' => __('Warranty'),
            'reviews' => __('Reviews'),
            'faq' => __('FAQ'),
        ];
        $policyLinks = collect($policyLinks ?? []);
        $warrantyPolicyLink = $policyLinks->first(fn($link) => $link['label'] === 'Warranty');
        $canonicalProductUrl = app(\App\Support\Tenancy\TenantUrlGenerator::class)
            ->canonicalRoute(tenant(), 'storefront.product', [optional($translation)->slug]);

        $emiBasePrice = $product->variants->first()?->price ?? 0;
        $emiFromMonthly = $product->emiPlans->isNotEmpty()
            ? $product->emiPlans
                ->map(fn($plan) => round(($emiBasePrice * (1 + (float) $plan->interest_rate / 100)) / $plan->tenure_months))
                ->min()
            : null;
        $emiHasZero = $product->emiPlans->contains(fn($plan) => (float) $plan->interest_rate === 0.0);
        $productName = optional($translation)->name ?? 'Product';
        $pdpI18n = [
            'preOrder' => __('Pre-Order'),
            'available' => __('available'),
            'discontinued' => __('Discontinued'),
            'backorder' => __('Backorder'),
            'outOfStock' => __('Out of Stock'),
            'inStock' => __('In Stock'),
            'lowStock' => __('Low Stock'),
            'unavailable' => __('Unavailable'),
            'adding' => __('Adding…'),
            'preOrderNow' => __('Pre-Order Now'),
            'backorderNow' => __('Backorder Now'),
            'addToCart' => __('Add to Cart'),
            'buyNow' => __('Buy Now'),
            'selectOptions' => __('Select Options'),
        ];
    @endphp

    <x-seo.meta :seo="$seo" />

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

    <div class="{{ \App\Support\IndustryConfig::currentGet('ui.container_class', 'max-w-7xl mx-auto') }} px-4 sm:px-6 lg:px-8 py-8 pb-24 lg:pb-8" x-data="productDetail(@js($variantsData), @js($productImages), @js($dimensions), @js($initialVariantId), @js($isWishlisted), @js($isComparing), @js($emiData), @js($requiresSelection), @js($product->sell_by_unit ?? '1.000'), @js(tenant()->currency ?? 'BDT'), @js($pdpI18n))" x-init="init()">
        <nav class="text-sm text-gray-500 mb-6" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            @if ($product->category)
                <span class="mx-1">/</span>
                <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.category', [$product->category->slug]) }}"
                    class="hover:text-[var(--brand)]">{{ $product->category->name }}</a>
            @endif
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ ($product->translation() ?? $product->translation('en'))?->name }}</span>
        </nav>

        <x-dynamic-component
            :component="\App\Support\IndustryConfig::currentGet('ui.pdp_component', 'storefront.products.pdp-default')"
            :product="$product"
            :translation="$translation"
            :productDescription="$productDescription"
            :specificationGroups="$specificationGroups"
            :navSections="$navSections"
            :navLabels="$navLabels"
            :policyLinks="$policyLinks"
            :shippingMethods="$shippingMethods"
            :paymentMethods="$paymentMethods"
            :relatedCards="$relatedCards"
            :crossSellCards="$crossSellCards"
            :upsellCards="$upsellCards"
            :frequentlyBoughtCards="$frequentlyBoughtCards"
            :compatibleAccessoryCards="$compatibleAccessoryCards"
            :recentlyViewedCards="$recentlyViewedCards"
            :isWishlisted="$isWishlisted ?? false"
            :isComparing="$isComparing ?? false"
            :emiData="$emiData ?? []"
            :emiFromMonthly="$emiFromMonthly ?? null"
            :emiHasZero="$emiHasZero ?? false"
            :emiBasePrice="$emiBasePrice ?? 0"
            :showDescription="$showDescription ?? false"
            :showWarranty="$showWarranty ?? false"
            :showFaqs="$showFaqs ?? false"
        />

        @if (!empty($relatedBlogPosts) && $relatedBlogPosts->isNotEmpty())
            @include('storefront.partials.blog-rail', ['posts' => $relatedBlogPosts, 'title' => __('Related Articles')])
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
            emiPlans, requiresSelection, sellByUnit, currency, i18n) {
            return {
                ...variantSelectionState(variants, dimensions, requiresSelection, initialId),
                productImages,
                dimensions,
                currency: currency || 'BDT',
                i18n: i18n || {},
                selected: {},
                activeImage: null,
                loadedImages: {},
                erroredImages: {},
                lightboxOpen: false,
                currentVariantId: initialId,
                unavailable: false,
                quantity: parseFloat(sellByUnit) > 0 ? parseFloat(sellByUnit) : 1,
                sellByUnit: parseFloat(sellByUnit) > 0 ? parseFloat(sellByUnit) : 1,
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
                galleryTouchStartX: 0,
                galleryTouchStartY: 0,
                galleryTouchDeltaX: 0,
                galleryDragging: false,

                init() {
                    this.$store.wishlist.seed({{ $product->id }}, initialWishlisted);

                    if (this.requiresSelection) {
                        // Auto-select first purchasable active variant so PDP shows exact
                        // price/image/availability immediately (shared engine).
                        const firstValid = this.activeVariants().find(v => v.purchasable) ?? this.activeVariants()[0] ?? null;
                        if (firstValid) {
                            this.currentVariantId = firstValid.id;
                            this.dimensions.forEach(d => {
                                if (firstValid.dims[d.code] !== undefined && firstValid.dims[d.code] !== null) {
                                    this.selected[d.code] = firstValid.dims[d.code];
                                }
                            });
                            this.unavailable = false;
                        } else {
                            this.currentVariantId = null;
                        }
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
                showSticky() {
                    return this.current() !== null || (this.requiresSelection && !this.selectionComplete());
                },
                updateVariant() {
                    const match = this.current();

                    if (match) {
                        this.unavailable = false;
                        this.currentVariantId = match.id;
                    } else if (this.requiresSelection && !this.selectionComplete()) {
                        // Still picking options â€” currentImages() falls back to
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
                galleryIndex() {
                    return this.currentImages().findIndex(i => i.src === this.resolvedActiveImage());
                },
                galleryPrev() {
                    const images = this.currentImages();
                    const idx = images.findIndex(i => i.src === this.resolvedActiveImage());
                    if (idx > 0) this.activeImage = images[idx - 1].src;
                },
                galleryNext() {
                    const images = this.currentImages();
                    const idx = images.findIndex(i => i.src === this.resolvedActiveImage());
                    if (idx >= 0 && idx < images.length - 1) this.activeImage = images[idx + 1].src;
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
                priceRange() {
                    const variants = this.activeVariants();
                    if (!variants.length) return null;
                    const prices = variants.map(v => v.price);
                    const min = Math.min(...prices);
                    const max = Math.max(...prices);
                    if (min === max) return { min, max, isRange: false };
                    return { min, max, isRange: true };
                },
                priceRangeLabel() {
                    const range = this.priceRange();
                    if (!range) return '';
                    if (!range.isRange) return this.formatPrice(range.min);
                    return this.formatPrice(range.min) + ' – ' + this.formatPrice(range.max);
                },
                availabilityLabel() {
                    const v = this.current();
                    if (!v) return '';
                    if (v.purchase_state === 'preorder') return this.i18n.preOrder || 'Pre-Order';
                    if (v.purchase_state === 'dropship') return this.i18n.available || 'Available';
                    if (v.purchase_state === 'discontinued') return this.i18n.discontinued || 'Discontinued';
                    if (v.purchase_state === 'out_of_stock') return v.backorder_policy ? (this.i18n.backorder || 'Backorder') : (this.i18n.outOfStock || 'Out of Stock');
                    return this.i18n.inStock || 'In Stock';
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
                    if (v.purchase_state === 'out_of_stock' && !v.backorder_policy) return 'Back in stock ' + v.expected_available_at;
                    return '';
                },
                ctaLabel() {
                    const v = this.current();
                    if (this.cartLoading) return this.i18n.adding || 'Adding…';
                    if (!v) return this.i18n.unavailable || 'Unavailable';
                    if (!v.purchasable) {
                        return v.purchase_state === 'discontinued' ? (this.i18n.discontinued || 'Discontinued') : (this.i18n.outOfStock || 'Out of Stock');
                    }
                    if (v.purchase_state === 'preorder') return this.i18n.preOrderNow || 'Pre-Order Now';
                    if (v.purchase_state === 'out_of_stock') {
                        return v.backorder_policy === 'notify' ? (this.i18n.backorderNow || 'Backorder Now') : (this.i18n.addToCart || 'Add to Cart');
                    }
                    return this.i18n.addToCart || 'Add to Cart';
                },
                formatPrice(cents) {
                    return window.money(cents, this.currency || 'BDT', document.documentElement.lang || 'en', true);
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
                onGalleryTouchStart(e) {
                    if (this.currentImages().length <= 1) return;
                    this.galleryTouchStartX = e.touches[0].clientX;
                    this.galleryTouchStartY = e.touches[0].clientY;
                    this.galleryDragging = true;
                    this.galleryTouchDeltaX = 0;
                },
                onGalleryTouchMove(e) {
                    if (!this.galleryDragging || this.currentImages().length <= 1) return;
                    const dx = e.touches[0].clientX - this.galleryTouchStartX;
                    const dy = e.touches[0].clientY - this.galleryTouchStartY;
                    if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 10) {
                        this.galleryDragging = false;
                        return;
                    }
                    this.galleryTouchDeltaX = dx;
                },
                onGalleryTouchEnd(e) {
                    if (!this.galleryDragging || this.currentImages().length <= 1) return;
                    this.galleryDragging = false;
                    const threshold = 40;
                    const images = this.currentImages();
                    const idx = images.findIndex(img => img.src === this.resolvedActiveImage());
                    if (this.galleryTouchDeltaX < -threshold && idx < images.length - 1) {
                        this.activeImage = images[idx + 1].src;
                    } else if (this.galleryTouchDeltaX > threshold && idx > 0) {
                        this.activeImage = images[idx - 1].src;
                    }
                    this.galleryTouchDeltaX = 0;
                },
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').content;
                },
                addToCart() {
                    const v = this.current();
                    if (!v || !v.purchasable) return;
                    this.cartLoading = true;
                    // Unify with product-card: use global Alpine store for optimistic + server-reconciled badge
                    const qty = this.quantity;
                    const vid = this.currentVariantId;
                    const store = this.$store?.cart;
                    if (store && typeof store.add === 'function') {
                        store.add(vid, qty).finally(() => this.cartLoading = false);
                    } else {
                        fetch('{{ route('storefront.cart.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken(),
                                    Accept: 'application/json'
                                },
                                body: JSON.stringify({ product_variant_id: vid, quantity: qty }),
                            })
                            .then(r => { if (!r.ok) throw new Error(); return r; })
                            .then(() => { this.toast('Added to cart'); if (window.Livewire) window.Livewire.dispatch('cart-updated'); })
                            .catch(() => this.toast('Could not add to cart — please try again', 'error'))
                            .finally(() => this.cartLoading = false);
                    }
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


