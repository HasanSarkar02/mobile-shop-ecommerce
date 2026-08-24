// resources/js/app.js
//
// Livewire v4 bundles its own Alpine instance internally. Importing Alpine
// separately from the 'alpinejs' package (as this file used to) creates a
// SECOND, independent Alpine instance alongside Livewire's — plugins/state
// registered on one are invisible to the other, and Livewire's own
// x-data-driven internals stop working correctly.
//
// The correct pattern (per Livewire's own docs, "manually bundling Alpine"):
// import Alpine from Livewire's ESM build, register plugins on that same
// instance, then let Livewire.start() start Alpine too. Never call
// Alpine.start() yourself here — see layout.blade.php, which uses
// @livewireScriptConfig (not @livewireScripts) to suppress Livewire's own
// auto-injected script tag in favor of this bundle.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Shared variant-selection engine (F.7): single source of truth for
// dimension-driven selection. Both the PDP (`productDetail`) and the card
// variant modal reuse this — no second implementation.
window.variantSelectionState = function (variants, dimensions, requiresSelection, initialVariantId) {
    return {
        variants: variants || [],
        dimensions: dimensions || [],
        requiresSelection: !!requiresSelection,
        selected: {},
        currentVariantId: initialVariantId || null,
        unavailable: false,
        activeVariants() {
            return this.variants.filter((v) => v.is_active);
        },
        missingDimensions() {
            if (!this.requiresSelection) return [];
            return this.dimensions.filter((d) => this.selected[d.code] === undefined || this.selected[d.code] === null);
        },
        selectionComplete() {
            return this.missingDimensions().length === 0;
        },
        selectionIssueType() {
            if (!this.requiresSelection) return null;
            if (!this.selectionComplete()) return 'incomplete';
            if (!this.current()) return 'invalid';
            return null;
        },
        selectionMessage() {
            if (this.requiresSelection && !this.selectionComplete()) {
                const missing = this.missingDimensions().map((d) => d.label);
                if (missing.length === 0) return 'Please select all product options';
                return 'Please select ' + missing.join(' and ');
            }
            return 'This combination of options is not available.';
        },
        current() {
            if (this.unavailable) return null;
            if (this.requiresSelection) {
                if (!this.selectionComplete()) return null;
                const matches = this.activeVariants().filter((v) => this.dimensions.every((d) => v.dims[d.code] === this.selected[d.code]));
                return matches.length === 1 ? matches[0] : null;
            }
            return this.activeVariants().find((v) => v.id === this.currentVariantId) ?? null;
        },
        updateVariant() {
            const match = this.current();
            if (match) {
                this.unavailable = false;
                this.currentVariantId = match.id;
            } else if (this.requiresSelection && !this.selectionComplete()) {
                this.unavailable = false;
                this.currentVariantId = null;
            } else {
                this.unavailable = true;
                this.currentVariantId = null;
            }
        },
        dimensionOptions(code) {
            return [...new Set(this.activeVariants().map((v) => v.dims[code]).filter((v) => v !== undefined && v !== null))];
        },
        formatPrice(cents) {
            return '৳' + Math.round(cents / 100).toLocaleString();
        },
        ctaLabel() {
            const v = this.current();
            if (!v) {
                if (this.requiresSelection && !this.selectionComplete()) return 'Select Options';
                return 'Unavailable';
            }
            if (!v.purchasable) {
                return v.purchase_state === 'discontinued' ? 'Discontinued' : 'Out of Stock';
            }
            if (v.purchase_state === 'preorder') return 'Pre-Order Now';
            if (v.purchase_state === 'out_of_stock' && v.backorder_policy === 'notify') return 'Backorder Now';
            return 'Add to Cart';
        },
    };
};

// Global UI state that needs to be triggered from more than one place in the
// DOM tree (the mobile header's hamburger button, and the "Categories" tab
// in the mobile bottom nav both open the same drawer, but sit in separate
// x-data scopes as siblings in layout.blade.php — a plain local x-data
// variable can't coordinate across them). Registered before Livewire.start()
// so it exists for the very first paint.
document.addEventListener('alpine:init', () => {
    Alpine.store('ui', {
        mobileMenuOpen: false,
    });

    // Shared cart-store state for the product-card "Add to Cart" CTA. One store
    // keeps a per-variant pending guard so rapid clicks on the same card (or
    // across the many cards on a page) can never double-submit, and every card
    // surfaces the same loading/disabled/toast behaviour. The request goes to
    // the existing CartController::store endpoint; CartService remains the
    // single authoritative validation layer.
    Alpine.store('cart', {
        pending: {},

        endpoint() {
            const url = document.body.dataset.cartStore;
            if (!url && window.console) {
                console.error('[cart store] Missing data-cart-store attribute on <body> — Add to Cart cannot submit.');
            }
            return url || '';
        },

        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        add(variantId, quantity = 1) {
            if (this.pending[variantId]) {
                return Promise.resolve(false);
            }

            if (!this.endpoint()) {
                this.toast('Could not add to cart — please try again', 'error');
                return Promise.resolve(false);
            }

            this.pending[variantId] = true;

            return fetch(this.endpoint(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        product_variant_id: variantId,
                        quantity,
                    }),
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    this.toast('Added to cart');
                    if (window.Livewire) window.Livewire.dispatch('cart-updated');
                })
                .catch(() => {
                    this.toast('Could not add to cart — please try again', 'error');
                })
                .finally(() => {
                    this.pending[variantId] = false;
                });
        },
    });

    // Offer countdown used by the reusable offers/countdown partial: segmented
    // days/hours/minutes/seconds tiles ticking once per second, flipping to an
    // "ended" state cleanly when the deadline passes.
    Alpine.data('offerCountdown', (endsAtMs) => ({
        live: Date.now() < endsAtMs,
        days: '00',
        hours: '00',
        minutes: '00',
        seconds: '00',
        timer: null,

        init() {
            if (!this.live) return;
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            const diff = endsAtMs - Date.now();
            if (diff <= 0) {
                this.live = false;
                clearInterval(this.timer);
                return;
            }
            this.days = String(Math.floor(diff / 86400000)).padStart(2, '0');
            this.hours = String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0');
            this.minutes = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
            this.seconds = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
        },
    }));

    // Shared wishlist state. Every product card and the PDP buy-box wishlist
    // button read/write this one store, so all instances of the same product
    // on a page stay in sync and the header/mobile count badge reacts to
    // changes. Toggle is optimistic: flip immediately, POST to the existing
    // endpoint, reconcile with the server's returned `wishlisted` value, and
    // roll back on any HTTP/network error.
    Alpine.store('wishlist', {
        state: {},
        pending: {},
        count: null,

        seed(productId, wishlisted) {
            if (!(productId in this.state)) {
                this.state[productId] = !!wishlisted;
            }
        },

        seedCount(count) {
            if (this.count === null) {
                this.count = Number(count) || 0;
            }
        },

        isWishlisted(productId) {
            return this.state[productId] === true;
        },

        endpoint() {
            const url = document.body.dataset.wishlistToggle;
            if (!url && window.console) {
                console.error('[wishlist store] Missing data-wishlist-toggle attribute on <body> — wishlist cannot submit.');
            }
            return url || '';
        },

        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        syncCount(delta) {
            if (this.count !== null) {
                this.count = Math.max(0, this.count + delta);
            }
        },

        toggle(productId) {
            if (this.pending[productId]) {
                return Promise.resolve(false);
            }

            if (!this.endpoint()) {
                this.toast('Could not update wishlist — please try again', 'error');
                return Promise.resolve(false);
            }

            this.pending[productId] = true;
            const wasWishlisted = this.isWishlisted(productId);

            // Optimistic flip so the UI responds immediately.
            this.state[productId] = !wasWishlisted;

            return fetch(this.endpoint(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ product_id: productId }),
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    const wishlisted = !!data.wishlisted;
                    this.state[productId] = wishlisted;
                    if (wishlisted !== wasWishlisted) {
                        this.syncCount(wishlisted ? 1 : -1);
                    }
                    this.toast(wishlisted ? 'Added to wishlist' : 'Removed from wishlist');
                    if (window.Livewire) window.Livewire.dispatch('wishlist-updated');
                })
                .catch(() => {
                    // Roll back the optimistic flip on HTTP or network error.
                    this.state[productId] = wasWishlisted;
                    this.toast('Could not update wishlist — please try again', 'error');
                })
                .finally(() => {
                    this.pending[productId] = false;
                });
        },
    });
});

Livewire.start();