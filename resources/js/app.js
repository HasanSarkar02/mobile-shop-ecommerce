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