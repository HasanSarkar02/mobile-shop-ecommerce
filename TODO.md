# TODO / Project Checklist

Project: **Mobile Shop E-commerce** — multi-tenant SaaS e-commerce platform.

Legend: `[x]` completed · `[~]` in progress · `[ ]` pending

---

## Completed

### Platform / SaaS
- [x] Tenant management (subdomains, custom domains, status)
- [x] Plans, subscriptions, trials, plan change requests
- [x] Platform marketing home + signup flow
- [x] Filament Platform panel (tenants, plans, plan change requests, stats)
- [x] Billing page + subscription events/logging
- [x] TenantRegistrationService, SubscriptionService

### Store admin (Filament)
- [x] Products: variants, attributes/options, tags, translations, relations, EMI plans, media
- [x] Inventory: locations, stock items/movements, serial numbers (IMEI), restock, low-stock widget
- [x] Orders, payments, fulfillments, order events, reservation expiry, double-submission guard
- [x] Customers & staff (owner-only scoping)
- [x] Coupons: scopes, eligibility, redemptions, currency-consistent validation
- [x] Payment methods (driver-based) + shipping methods
- [x] Content: brands, categories, collections, banners, campaigns, announcements, menus, homepage sections, static pages, redirects, FAQs, blog, reviews
- [x] Notification templates + logs
- [x] Theme/store/global settings
- [x] Dashboard widgets

### Storefront
- [x] Homepage builder (banner carousel, category grid, product grid, custom HTML, trust badges, newsletter CTA)
- [x] Catalog: categories, brands, collections, product pages (variants, reviews, related)
- [x] Product listing: facets/filters, sorting, filter chips
- [x] Search + live suggestions
- [x] Cart, MiniCart, checkout, order confirmation
- [x] Payments: SSLCommerz flow (pay/success/fail/cancel/IPN, idempotent)
- [x] Wishlist, compare, recently viewed
- [x] Customer auth + account (dashboard, orders, addresses, profile, password)
- [x] Order tracking, FAQ, blog, static pages, sitemap.xml, robots.txt
- [x] Newsletter subscribe endpoint + CTA section

### Infrastructure
- [x] 91 migrations / 60 models
- [x] OrderService, CartService, InventoryService, CouponService, PaymentGatewayService, Wishlist/Compare/RecentlyViewed/ProductListing/Notification/Redirect services, SequenceGenerator
- [x] Core tests: order, inventory, coupon (+currency), payment gateway, double-submission, idempotency, owner-only auth, deletion protection, tenancy fail-closed, queue-after-commit

---

## In Progress

- [~] Storefront frontend polish
  - [~] `ProductCatalog` Livewire (product listing + facets) + `product-catalog.blade.php`
  - [~] Storefront brands index page (`storefront/brands/index.blade.php`)
  - [~] Storefront categories index page (`storefront/categories/index.blade.php`)
  - [~] Newsletter CTA homepage section + controller wiring
  - [~] Storefront layout, header, theme toggle, mini-cart updates
- [~] Custom HTML section sanitizer
  - [x] `App\Support\Purifier\StorefrontPurifier` + `SafeCssValue` (modern CSS preserved, XSS blocked)
  - [x] `config/purifier.php` storefront settings updated
  - [x] Blade switched to `StorefrontPurifier::clean()`
  - [x] Verified via CLI test (flex/grid/gap/border-radius/box-shadow/transform survive; script, javascript:, expression, -moz-binding, behavior, data: blocked)
  - [ ] Commit all in-progress work (see `git status`)

---

## Backlog

### Payments & billing
- [ ] Add more payment gateway drivers: bKash, Nagad, Aamarpay, Stripe/card, COD (interface + SSLCommerz exist)
- [ ] Payment refunds / partial refunds from admin
- [ ] Invoice generation (PDF)
- [ ] Subscription auto-renewal / dunning emails / plan upgrade flow end-to-end

### Notifications & marketing
- [ ] Wire notification templates to real email/SMS delivery (currently templates + logs only)
- [ ] Newsletter subscriber management UI in admin
- [ ] Newsletter double opt-in + unsubscribe link
- [ ] Email campaigns (e.g. order status, abandoned cart)

### Catalog & storefront
- [ ] Compare/wishlist session persistence across devices (account-bound)
- [ ] Recently viewed pruning + widget
- [ ] Product quick-view / gallery zoom / 360 images
- [ ] Breadcrumb schema + richer structured data (review schema)
- [ ] Lazy-load / infinite scroll on product listing
- [ ] Multi-currency support (prices currently single currency per store)
- [ ] Reviews: moderation workflow, verified-buyer flag, reply

### Search
- [ ] Switch Laravel Scout from `database` driver to Meilisearch/Algolia
- [ ] Search typo tolerance, synonyms, facets-from-search
- [ ] Product filters persisted in URL query strings (verify)

### Ops & hardening
- [ ] Run full test suite (`composer test`) and fix any failures
- [ ] Static analysis: run Larastan/Pint and address findings
- [ ] Production config: queue driver (database→redis), cache driver, config/route caching, HTTPS
- [ ] Deployment pipeline (Dockerfile / Forge / CI)
- [ ] Backups, logging/monitoring, rate-limit review
- [ ] Admin audit log UI

### Data & demo
- [ ] Rich demo seeder (multiple categories, products, images, reviews)
- [ ] Admin bulk import/export (CSV) for products & stock
- [ ] Stock reorder-point alerts / email notifications

### Long-term / future
- [ ] Public REST API (mobile app)
- [ ] PWA / app shell
- [ ] i18n for storefront UI (product translations exist)
- [ ] Multi-location shipping rules & zone-based rates
- [ ] Analytics dashboard (revenue, conversion, top products)
