import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Shared money helper — Bangla digits strictly when active locale is bn
window.money = function (cents, currency = 'BDT', locale = null, withTrailingZeros = true) {
    const loc = locale || document.documentElement.lang || 'en';
    const tag = loc === 'bn' ? 'bn-BD' : 'en-BD';
    const symbol = { BDT: '৳', USD: '$', EUR: '€', GBP: '£', INR: '₹', PKR: '₨' }[currency] || (currency + ' ');
    const major = cents / 100;
    const opts = {
        minimumFractionDigits: withTrailingZeros ? 2 : 0,
        maximumFractionDigits: withTrailingZeros ? 2 : 0,
    };
    let formatted = new Intl.NumberFormat(tag, opts).format(major);
    // Keep Bangla digits when locale is bn, otherwise force Western
    if (loc !== 'bn') {
        formatted = formatted.replace(/[০-৯]/g, (d) => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));
    }
    return symbol + formatted;
};

window.moneyWithoutTrailingZeros = function (cents, currency = 'BDT', locale = null) {
    return window.money(cents, currency, locale, false);
};

// Shared variant-selection engine
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
                const matches = this.activeVariants().filter((v) =>
                    this.dimensions.every((d) => v.dims[d.code] === this.selected[d.code])
                );
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
            return window.money(cents, 'BDT', document.documentElement.lang || 'en', true);
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

function registerStores() {
    // Prevent double registration
    if (Alpine.store('ui') !== undefined) {
        console.log('[stores] already registered – skipping');
        return;
    }

    console.log('[stores] registering ui / cart / wishlist');

    Alpine.store('ui', {
        mobileMenuOpen: false,
    });

    Alpine.store('cart', {
        pending: {},
        count: null,

        seedCount(count) {
            if (this.count === null) {
                this.count = Number(count) || 0;
            }
        },

        syncCount(delta) {
            this.count = Math.max(0, (this.count ?? 0) + Number(delta));
        },

        endpoint() {
            const url = document.body?.dataset?.cartStore || '';
            if (!url) {
                console.error('[cart] Missing data-cart-store on <body>');
            }
            return url;
        },

        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        add(variantId, quantity = 1, explicitUrl = null) {
            if (this.pending[variantId]) return Promise.resolve(false);

            const url = explicitUrl || this.endpoint();
            if (!url) {
                this.pending[variantId] = false;
                this.toast('Could not add to cart — please try again', 'error');
                return Promise.resolve(false);
            }

            this.pending[variantId] = true;
            const qty = Number(quantity) || 1;
            this.syncCount(qty);
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: this.count } }));
            if (window.Livewire) window.Livewire.dispatch('cart-updated');

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    product_variant_id: variantId,
                    quantity: qty,
                }),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const text = await response.text();
                        throw new Error(text || 'Request failed');
                    }
                    // Try parse JSON for cart_count reconciliation
                    let data = {};
                    try {
                        const txt = await response.clone().text();
                        data = txt ? JSON.parse(txt) : {};
                    } catch {}
                    return data;
                })
                .then((data) => {
                    // Server reconciliation: override with authoritative count if provided
                    if (data && data.cart_count !== undefined && data.cart_count !== null) {
                        this.count = Number(data.cart_count);
                    }
                    this.toast('Added to cart');
                    if (window.Livewire) window.Livewire.dispatch('cart-updated');
                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: this.count } }));
                })
                .catch(() => {
                    this.syncCount(-qty);
                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: this.count } }));
                    if (window.Livewire) window.Livewire.dispatch('cart-updated');
                    this.toast('Could not add to cart — please try again', 'error');
                })
                .finally(() => {
                    this.pending[variantId] = false;
                });
        },
    });

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

    Alpine.store('wishlist', {
        state: {},
        pending: {},
        count: null,

        seed(productId, wishlisted) {
            const id = String(productId); // normalize key
            if (!(id in this.state)) {
                this.state[id] = !!wishlisted;
                console.log('[wishlist] seeded', id, '→', this.state[id]);
            }
        },

        seedCount(count) {
            if (this.count === null) {
                this.count = Number(count) || 0;
            }
        },

        isWishlisted(productId) {
            return this.state[String(productId)] === true;
        },

        endpoint() {
            const url = document.body?.dataset?.wishlistToggle || '';
            if (!url) {
                console.error('[wishlist] Missing data-wishlist-toggle on <body>');
            }
            return url;
        },

        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.content : '';
            if (!token) console.error('[wishlist] CSRF token meta missing');
            return token;
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        syncCount(delta) {
            this.count = Math.max(0, (this.count ?? 0) + Number(delta));
        },

        toggle(productId) {
            const id = String(productId);
            console.log('[wishlist] toggle called for', id);

            if (this.pending[id]) {
                console.log('[wishlist] already pending – ignored');
                return Promise.resolve(false);
            }

            const url = this.endpoint();
            if (!url) {
                this.toast('Could not update wishlist — please try again', 'error');
                return Promise.resolve(false);
            }

            this.pending[id] = true;
            const wasWishlisted = this.isWishlisted(id);

            // Optimistic flip
            this.state[id] = !wasWishlisted;
            console.log('[wishlist] optimistic →', this.state[id]);

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ product_id: Number(productId) || productId }),
                credentials: 'same-origin',
            })
                .then(async (response) => {
                    console.log('[wishlist] response status', response.status);
                    if (!response.ok) {
                        const text = await response.text();
                        console.error('[wishlist] non-OK response body:', text.slice(0, 500));
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then((data) => {
                    console.log('[wishlist] server replied', data);
                    const wishlisted = !!data.wishlisted;
                    this.state[id] = wishlisted;
                    if (wishlisted !== wasWishlisted) {
                        this.syncCount(wishlisted ? 1 : -1);
                    }
                    this.toast(wishlisted ? 'Added to wishlist' : 'Removed from wishlist');
                    if (window.Livewire) window.Livewire.dispatch('wishlist-updated');
                })
                .catch((err) => {
                    console.error('[wishlist] toggle failed', err);
                    // Roll back
                    this.state[id] = wasWishlisted;
                    this.toast('Could not update wishlist — please try again', 'error');
                })
                .finally(() => {
                    this.pending[id] = false;
                });
        },
    });

    console.log('[stores] registration complete. wishlist store exists?', !!Alpine.store('wishlist'));
}

// Register BEFORE Livewire.start() – this is the correct order for the ESM build
registerStores();

// Safety nets
document.addEventListener('alpine:init', () => {
    console.log('[alpine:init] fired');
    registerStores();
});

if (typeof window !== 'undefined' && window.Alpine && typeof window.Alpine.store === 'function') {
    if (window.Alpine.store('ui') === undefined) {
        console.log('[fallback] registering on window.Alpine');
        registerStores();
    }
}

document.addEventListener('livewire:initialized', () => {
    console.log('[livewire:initialized]');
    if (window.Livewire) {
        window.Livewire.hook('morph.updated', ({ el }) => {
            if (window.Alpine?.initTree) {
                window.Alpine.initTree(el);
            }
        });
    }
});

Livewire.start();
console.log('[app.js] Livewire.start() called');