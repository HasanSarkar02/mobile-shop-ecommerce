# Mobile Shop E-commerce

A multi-tenant **SaaS e-commerce platform** for selling mobile phones and accessories. Each store (tenant) gets its own subdomain, its own storefront, and its own admin panel. The platform operator manages plans and tenants.

Built with **Laravel 13**, **Filament 5** (admin panels), **Livewire**, **Alpine.js**, and **Tailwind CSS 4**.

---

## Tech Stack

| Area       | Technology                                            |
| ---------- | ----------------------------------------------------- |
| Backend    | PHP ^8.3, Laravel ^13.8                               |
| Admin      | Filament ^5.6 (Store + Platform panels)               |
| Frontend   | Livewire, Alpine.js ^3, Tailwind CSS ^4, Vite ^8      |
| Database   | MySQL (default), Eloquent, 91 migrations, 60 models   |
| Search     | Laravel Scout (database driver)                       |
| Sanitizer  | mews/purifier (HTMLPurifier) — extended for storefront |
| Media      | Spatie Media Library                                  |
| Logging    | Spatie Activity Log                                   |
| Tests      | Pest ^4 (Feature + Unit)                              |

---

## Architecture

The app is one codebase running **three surfaces**:

```
┌─────────────────────────────────────────────────────────────┐
│  PLATFORM (central)                                          │
│  • Marketing home + signup (Livewire)                        │
│  • Filament "Platform" panel: tenants, plans, plan changes   │
├─────────────────────────────────────────────────────────────┤
│  STORE ADMIN (per tenant, Filament "Store" panel)            │
│  • Products, inventory, orders, coupons, content, ...        │
├─────────────────────────────────────────────────────────────┤
│  STOREFRONT (public, per tenant subdomain)                   │
│  • Catalog, search, cart, checkout, account, payments        │
└─────────────────────────────────────────────────────────────┘
```

- **Multi-tenancy** is subdomain-based. `routes/web.php` has a `central` middleware group and a `tenant` group that loads `routes/tenant.php`.
- Tenant context is resolved via `App\Support\Tenancy\Tenancy` and every tenant-scoped model is filtered by `tenant_id`.
- Admin panels enforce owner-only access through `RestrictsToOwner`.
- The storefront is theme-driven: the homepage is built from editable **homepage sections** (banner carousel, category grid, product grid, custom HTML, newsletter CTA, trust badges).

---

## Setup

Requirements: PHP ^8.3, Composer, Node 20+, MySQL.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# edit .env (DB_*, APP_URL, APP_DOMAIN, DEPLOYMENT_MODE, and proxy/HTTPS settings)

# 3. Database
php artisan migrate
php artisan db:seed

# 4. Build assets
npm run build          # or npm run dev

# 5. Serve (dev convenience)
composer dev           # runs server, queue worker, pail logs, vite
```

The seeder creates a **Demo Store** on subdomain `demo` with sample data:

| Role              | Email                 | Password  |
| ----------------- | --------------------- | --------- |
| Platform admin    | `admin@hasanmobileshop.com` | random — generated and printed to the console during `db:seed` |
| Store owner       | `owner@demo.test`     | `password` |
| Store customer    | `customer@demo.test`  | `password` |

Add `demo.mobile-shop-ecommerce.test` (and your `APP_URL` host) to your hosts file / local resolver, then open the storefront.

### Platform Admin management

Platform Admins are managed from the **Platform panel → Platform Admins** resource (SaaS mode only). The first Platform Admin is seeded with a randomly generated password. Additional admins are invited by an active Platform Admin:

1. An invitation link is emailed to the new admin (the token is stored as a SHA-256 digest only).
2. The invited admin sets their own password through the secure setup page.
3. Multi-factor authentication (authenticator app) is **mandatory** — the Platform panel redirects an admin without MFA to MFA setup and denies panel access until it is enrolled.
4. An active Platform Admin can activate/deactivate, revoke access, reset a password, or reset MFA for another admin. The last active Platform Admin cannot be deactivated or revoked.

Authoritative fields such as `tenant_id`, `role`, `is_platform_admin`, `is_active`, and `app_authentication_secret` are excluded from mass assignment and are only written through internal services.

### Deployment mode

The application defaults to SaaS mode:

```dotenv
DEPLOYMENT_MODE=saas
APP_DOMAIN=mobile-shop-ecommerce.test
```

SaaS mode resolves the central host, tenant subdomains, and registered custom domains. Custom-domain access also requires an active tenant subscription whose plan allows custom domains.

Dedicated mode uses the same tenant context and tenant-scoped models, but resolves exactly one configured tenant from one configured host:

```dotenv
DEPLOYMENT_MODE=dedicated
DEDICATED_TENANT_ID=1
DEDICATED_CANONICAL_HOST=store.example.com
APP_URL=https://store.example.com
APP_SCHEME=https
FORCE_HTTPS=true
```

`DEDICATED_TENANT_ID` must reference an already bootstrapped active tenant. The dedicated installer is not included yet. The Platform panel remains unavailable in dedicated mode.

When running behind a reverse proxy, configure only the proxy addresses or CIDRs that are allowed to provide forwarded host/scheme headers:

```dotenv
TRUSTED_PROXIES=10.0.0.10,10.0.0.0/24
TRUSTED_PROXY_HEADERS=all
```

Do not use a blanket trusted-proxy or allowed-host wildcard in production. Production deployments should use HTTPS URLs and set `FORCE_HTTPS=true` after proxy configuration has been verified.

> The seeder also wires a live-like `/auto-login/{user}` route for quick storefront testing.

---

## What's Completed

### Platform / SaaS (central)
- [x] Tenant management (subdomains, custom domains, status)
- [x] Plans, subscriptions, trials (14-day), plan change requests
- [x] Billing page per tenant, subscription events/logging
- [x] Platform marketing home + signup flow (`TenantSignupForm`)
- [x] Filament Platform panel: Tenants, Plans, Plan Change Requests, stats widget
- [x] `TenantRegistrationService`, `SubscriptionService`

### Store Admin (Filament Store panel)
- [x] Catalog: products, variants (SKU, price, cost, regions, fulfillment strategies, expected availability), attributes & options, tags, translations, relations, EMI plans, media
- [x] Inventory: locations, stock items, stock movements, serial numbers (IMEI), restock, low-stock widget
- [x] Orders: order lifecycle, payments, fulfillments, order events, reservation expiry, double-submission protection
- [x] Customers & staff, with owner-only scoping (`RestrictsToOwner`)
- [x] Coupons: scopes, customer eligibility, redemptions, currency-consistent validation
- [x] Payment methods (gateway-driver based), shipping methods
- [x] Content: brands, categories, collections, banners, campaigns, announcements, menus, homepage sections, static pages, redirects, FAQs, blog posts, product reviews
- [x] Notifications: templates + logs (wiring for delivery is pending — see TODO)
- [x] Theme settings, store settings, global store settings
- [x] Dashboard widgets (stats, recent orders, low stock)

### Storefront (public)
- [x] Homepage builder rendering (banner carousel, category grid, product grid, custom HTML, trust badges, newsletter CTA)
- [x] Catalog: categories list/detail, brands list/detail, collections, product pages (variants, reviews, related products)
- [x] Product listing with facets/filters (brand, price range, attributes), sorting, filter chips, `ProductListingService` + `FacetResolver`
- [x] Search with live suggestions (`/search/suggest`)
- [x] Cart (session cart, price/stock validation), MiniCart Livewire, cart page
- [x] Checkout (Livewire), order confirmation
- [x] Payments: pay/success/fail/cancel/IPN flow with idempotent callbacks; **SSLCommerz driver**
- [x] Compare list, wishlist, recently viewed products
- [x] Customer auth (login/register/logout), account dashboard, orders, addresses, profile & password
- [x] Order tracking, FAQ, blog, static pages, sitemap.xml, robots.txt
- [x] Newsletter subscription endpoint + CTA section
- [x] SEO meta partial, theme toggle, desktop/header layout components

### Services & infrastructure
- [x] `OrderService`, `CartService`, `InventoryService`, `CouponService`, `PaymentGatewayService` (+ driver interface), `WishlistService`, `CompareService`, `RecentlyViewedService`, `ProductListingService`, `NotificationService`, `RedirectService`, `SequenceGenerator`, `ProductService`, `ProductAttributeValueService`
- [x] 91 migrations / 60 models covering the full domain
- [x] Tests: order service, inventory, coupon (incl. currency), payment gateway, checkout double-submission, payment callback idempotency, owner-only authorization, deletion protection, tenancy fail-closed, queue-after-commit

---

## What's Left (summary)

See **[TODO.md](./TODO.md)** for the full task list.

- Commit the in-progress storefront work (product catalog Livewire, brands/categories index pages, newsletter section, custom-HTML sanitizer fix).
- Run the full test suite and fix any failures.
- Add more payment gateways (only SSLCommerz is implemented).
- Wire notification templates to real email/SMS delivery; add subscriber management + double opt-in.
- Move search from the database driver to Meilisearch/Algolia for production.
- Production hardening: queue/cache drivers, config caching, HTTPS, deployment.

---

## Project Structure (key folders)

```
app/
├─ Filament/
│  ├─ Platform/          # Platform admin panel
│  └─ Store/             # Per-tenant store admin panel
├─ Http/Controllers/Storefront/   # Public storefront controllers
├─ Livewire/             # CartPage, CheckoutPage, MiniCart, ProductCatalog, TenantSignupForm
├─ Models/               # 60 Eloquent models
├─ Services/             # Business logic services
│  └─ Storefront/        # Listing, facets, homepage rendering
├─ Support/              # Tenancy, Purifier (SafeCssValue, StorefrontPurifier), ...
└─ Filament/.../Resources # Admin CRUD resources
routes/
├─ web.php               # central + tenant groups
└─ tenant.php            # storefront routes
resources/views/
├─ platform/             # central marketing/signup
├─ storefront/           # public store
├─ filament/             # admin panel views
└─ livewire/             # Livewire component views
tests/
├─ Feature/              # service + tenancy + authorization tests
└─ Unit/
```

---

## Testing

```bash
composer test        # config:clear + pest
# or
php artisan test
```

---

## Notes & Conventions

- Money is stored as **integers** (minor units) to avoid float precision issues; coupon validation is currency-consistent (`CouponConCurrencyTest`).
- Storefront custom HTML is sanitized by `App\Support\Purifier\StorefrontPurifier`, which registers modern CSS properties (flex, grid, gap, shadows, transforms) onto HTMLPurifier's CSS definition while rejecting injection tokens (`url(...)`, `expression`, `-moz-binding`, etc.).
- Check `phpstan.neon`, `pint.json` for static analysis / style tooling.
