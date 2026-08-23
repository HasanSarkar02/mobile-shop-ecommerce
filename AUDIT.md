# AUDIT REPORT — mobile-shop-Ecommerce vs. reference sites

**Date:** 2026-08-20
**References:** [applegadgetsbd.com](https://www.applegadgetsbd.com) (AG) · [gadgetandgear.com](https://gadgetandgear.com) (GG)
**Scope:** storefront (tenant), admin (Filament Store + Platform panels), data model, payments, notifications, frontend build.
**Method:** direct inspection of `routes/tenant.php`, `routes/web.php`, `app/Filament/**`, `app/Models/*`, `app/Services/**`, `resources/views/storefront/**`, `config/*`, `.env`; verified against `TODO.md` and live reference site pages.

Verdicts: **EXISTS** = fully implemented · **PARTIAL** = implemented but incomplete · **MISSING** = not present.

> **Reconciliation — 2026-08-23.** The findings below are preserved as they stood on
> 2026-08-20; they are not rewritten. Six of them have since been disproved by shipped
> code and are annotated inline with **→ 2026-08-23:**. Verified against the tree at
> that date (suite green at 718/718, Pint clean, Larastan 0 errors):
>
> - Offers/campaigns, newsletter admin UI, outlets, WhatsApp widget and the refund
>   workflow are all now implemented with tests — see PLAN.md #10, #26, #27, #30, #32.
> - The "~200 modified + ~100 untracked files" working tree in §7 no longer exists; it
>   has been reconciled into logical commits.
>
> Anything **not** annotated is still believed accurate. Read an un-annotated
> **MISSING** as "was missing on 2026-08-20 and no evidence has been found since",
> not as a fresh verification.

---

## 1. Storefront — feature inventory

| Area | Verdict | Evidence |
|---|---|---|
| Home page (hero carousel, category grid, product rails, trust badges, newsletter CTA, custom HTML) | **EXISTS** | `app/Http/Controllers/Storefront/HomeController.php`; `resources/views/storefront/home.blade.php`; `app/Services/Storefront/HomepageSectionRenderer.php`; `resources/views/storefront/partials/sections/*` |
| Category + brand navigation / mega menu | **EXISTS** | `storefront/categories/index.blade.php`, `brands/index.blade.php`, listing controllers; menu system (`app/Models/Menu.php`, `MenuItem.php`) |
| Collections | **EXISTS** | `/collection/{slug}`, `storefront/collections/show.blade.php` |
| Product detail page (gallery, variant selection, spec/description/warranty tabs, related, recently viewed, share, trust strip) | **EXISTS** | `storefront/products/show.blade.php` (Alpine `productDetail`; gallery zoom/alt, sticky CTA, EMI modal); `Pdp*Test.php` suite |
| Product listing with facets/filters/sorting | **EXISTS** | `app/Livewire/ProductCatalog.php`; `storefront/partials/filter-sidebar.blade.php`, `filter-form`, `filter-chips`, `sort-select` |
| Search + live suggestions | **EXISTS** | `SearchController`, `SearchSuggestController`; Scout `database` driver (`.env:70`) |
| Cart + MiniCart + buy-now | **EXISTS** | `app/Livewire/CartPage.php`, `MiniCart.php`; `CartController` |
| Checkout + confirmation | **EXISTS** | `app/Livewire/CheckoutPage.php`; `storefront/checkout/show.blade.php`, `confirmation.blade.php` |
| Customer auth + account (dashboard, orders, addresses, profile, password) | **EXISTS** | `CustomerAuthController`, `Account*Controller`; `storefront/account/*` |
| Order tracking | **EXISTS** | `OrderTrackingController`; `/track-order` form + result views |
| Blog (public list + single post) | **EXISTS** | `/blog`, `/blog/{slug}`; `storefront/blog/index.blade.php`, `show.blade.php` |
| FAQ | **EXISTS** | `FaqController`; `storefront/faqs/index.blade.php` |
| Static pages (policies etc.) | **EXISTS** | `/page/{slug}`; `StaticPageController`; `storefront/pages/show.blade.php` |
| Compare | **EXISTS** | `CompareController` (show/toggle/remove/clear); `storefront/compare/show.blade.php`; `CompareBadge` Livewire |
| Wishlist | **EXISTS** | `WishlistController`; `storefront/wishlist/index.blade.php`; product-card toggle |
| Recently viewed | **EXISTS** | `RecentlyViewedProduct` model + `PdpRecentlyViewedTest`; widget pending pruning |
| Product reviews (submit + display) | **EXISTS** | `ProductReviewController::store`; PDP reviews block |
| Pre-order at variant level | **EXISTS (partial)** | `FulfillmentStrategy::Preorder`; PDP shows "Pre-Order Now" + expected availability; **no dedicated `/pre-order` listing page** |
| EMI (PDP display, 0% EMI, modal, admin plans) | **EXISTS** | `EmiPlan` model + `product_emi_plan` pivot; PDP EMI modal; `EmiPlanResource`; `PdpEmiTest` |
| Offers / campaigns | **PARTIAL** → **EXISTS** | `Campaign` model (status `draft/active/ended`, `starts_at/ends_at`) + `CampaignResource` exist; **no public `/offer` landing page with live countdown** (AG has this). **→ 2026-08-23:** shipped — `/offers` index + `/offer/{slug}` detail with Alpine D-H-M-S countdown, `campaign_product` pivot, `CampaignProductResolver`, campaign hero/card imagery with fallbacks; `OfferPageTest` (5) + `OfferCampaignProductsTest` (12) |
| Newsletter subscribe endpoint + CTA | **PARTIAL** → **EXISTS** | `NewsletterController::subscribe` + `NewsletterSubscriber` model + CTA section; **no admin subscriber UI**. **→ 2026-08-23:** shipped — Filament `NewsletterSubscriberResource` (list, delete + bulk delete, per-view and full CSV export) |
| Outlets / store locator | **MISSING** → **EXISTS** | No outlet model or `/outlet/*` pages (AG lists 5 outlets + store pages). **→ 2026-08-23:** shipped — `Outlet` model + `outlets` table, `/outlets` index, Filament `OutletResource`, footer locations link gated on `hasOutlets`; `OutletPageTest` |
| WhatsApp live-chat widget + per-product `wa.me` | **MISSING** → **EXISTS** | AG/GG both have it; not present. **→ 2026-08-23:** shipped — `whatsapp_widget_enabled` theme toggle drives the floating widget, PDP "Ask about this product on WhatsApp" via `App\Support\WhatsApp::url()`; `WhatsAppWidgetTest` |
| Mobile apps (iOS/Android) | **MISSING** | AG has both; long-term (public API) |
| sitemap.xml / robots.txt | **EXISTS** | `SitemapController`, `RobotsController` |
| Multi-currency | **PARTIAL** | Single BDT currency per store (`OrderService.php:185` hardcodes `currency_rate 1.0`) — acceptable vs both references (BDT-only) |

## 2. Admin (Filament Store panel) — feature inventory

| Area | Verdict | Evidence |
|---|---|---|
| Products (variants, attributes/options, tags, translations, relations, media, EMI plans) | **EXISTS** | `ProductResource`; `AttributeDefinitionResource`; `TagResource` |
| Inventory (locations, stock items/movements, serial numbers/IMEI, restock, low-stock) | **EXISTS** | `LocationResource`, `StockItemResource`, `StockMovementResource`, `SerialNumberResource`; `InventoryService` (deterministic locks) |
| Orders, payments, fulfillments, order events, receipt | **EXISTS** | `OrderResource`; `OrderReceiptController`; `PaymentGatewayService`; `OrderSerialLinkageTest`, `OrderAdminOperationsTest` |
| Customers & staff | **EXISTS** | `CustomerResource`, `StaffResource` |
| Coupons (scopes, eligibility, redemptions) | **EXISTS** | `CouponResource`, `CouponRedemptionResource`; `CouponService` |
| Payment methods + shipping methods | **EXISTS** | `PaymentMethodResource`, `ShippingMethodResource` |
| Content: brands, categories, collections, banners, campaigns, announcements, menus, homepage sections, static pages, redirects, FAQs, blog, reviews | **EXISTS** | Full resource set under `app/Filament/Store/Resources/` |
| Notification templates + logs | **EXISTS** | `NotificationTemplateResource`, `NotificationLogResource`; `SendNotificationJob` |
| Theme / store / global settings | **EXISTS** | `ThemeSettings`, `StoreSettings`, `GlobalStoreSettings` pages |
| Billing (subscription/charges view for tenant) | **EXISTS** | `BillingPage` |
| Dashboard widgets | **EXISTS** | store dashboard widgets |
| Product review moderation (approve/reject) | **EXISTS** | `ProductReviewResource` actions; `ReviewStatus` enum |
| Review reply + verified-buyer badge on storefront | **PARTIAL** | `is_verified_purchase` field + status exist; reply workflow + storefront verified badge not done (`TODO.md`) |

## 3. Platform (Filament Platform panel) — feature inventory

| Area | Verdict | Evidence |
|---|---|---|
| Tenants (CRUD, owners, domains relations) | **EXISTS** | `TenantResource` + `OwnersRelationManager`, `DomainsRelationManager` |
| Plans, subscriptions, charges, payments | **EXISTS** | `PlanResource`, `TenantSubscriptionResource`, `SubscriptionChargeResource`, `SubscriptionPaymentResource` |
| Plan change requests | **EXISTS** | `PlanChangeRequestResource` + workflow tests |
| Domains + DNS verification | **EXISTS** | `DomainResource`; `CheckDomainDnsVerification` job; `DomainManagementService` |
| Platform admins | **EXISTS** | `PlatformAdminResource` + invitation flow |
| Dashboard stats | **EXISTS** | `PlatformDashboard`, `PlatformStatsOverview` |
| Subscription auto-renewal / dunning / plan-upgrade end-to-end | **MISSING** | `TODO.md` backlog |
| Subscription charge automation (scheduled charges) | **PARTIAL** | `SubscriptionChargeService` exists; automation depth limited |

## 4. Payments & notifications

| Area | Verdict | Evidence |
|---|---|---|
| SSLCommerz flow (pay/success/fail/cancel/IPN, idempotent) | **EXISTS** | `PaymentController` routes; `SslcommerzDriver`; `PaymentCallbackIdempotencyTest`; `.env:73-75` |
| SSLCommerz credentials | **PARTIAL** | **sandbox placeholders** (`.env:73-75`) — not production |
| bKash / Nagad / Aamarpay / Stripe / COD drivers | **MISSING** | `config/payment_gateways.php:8` — "Future gateways"; driver interface established |
| Payment refunds / partial refunds | **MISSING** → **EXISTS** | `OrderService.php:327,489` — deferred; `OrderStatus` has no `Refunded`; dead `'refunded' => 'gray'` UI at `OrderResource.php:736`. **→ 2026-08-23:** shipped — `OrderStatus::Refunded` live, `OrderService::refund()` with `amountPaid`/`amountRefunded`, refund header action on `ViewOrder` validating amount against refundable and capturing method/reason/reference; the `'refunded' => 'gray'` UI is now real, not dead |
| Invoice PDF | **MISSING** | receipt page (HTML) exists; no PDF |
| Email delivery | **EXISTS** | `EmailChannelDriver` (Mail::raw) + templates/logs; live SMTP configured (`.env`, gitignored) |
| SMS delivery | **MISSING (stub)** | `SmsChannelDriver` logs `[SMS stub]` only (explicit placeholder) |
| Email/SMS campaign automation (abandoned cart, order status) | **MISSING** | `TODO.md` backlog |

## 5. Frontend build status

- **Stack:** Laravel Blade + Tailwind + Alpine + Livewire, server-rendered (references are Next.js/React — no need to match framework).
- **Verdict:** storefront is **feature-complete and functional**; the gap vs references is *depth/polish* (offer countdown pages, pre-order hub, outlets, WhatsApp chat, quick-view, infinite scroll) rather than missing core modules. **→ 2026-08-23:** offer countdown pages, outlets and WhatsApp chat are now shipped; the remaining polish gap is the pre-order hub, quick-view and infinite scroll.
- **In progress** (`TODO.md`): `ProductCatalog` Livewire polish, storefront brands/categories index pages, newsletter CTA wiring, layout/header/theme-toggle/mini-cart polish, Custom HTML section sanitizer (done, pending commit). **→ 2026-08-23:** all of these are committed; nothing here is "pending commit" any more.

## 6. Honest gap list vs references (priority-ranked)

1. ~~**Offers landing page** with live countdown timer + `/offer/{slug}` detail~~ — **done 2026-08-23.**
2. **Dedicated pre-order page** listing pre-order variants.
3. **Production payment gateway(s)** — SSLCommerz prod creds + bKash/Nagad/COD drivers.
4. ~~**Refund workflow** — `OrderStatus::Refunded`, OrderResource refund action, remove dead UI.~~ — **done 2026-08-23.**
5. ~~**Outlets / store locator** pages.~~ — **done 2026-08-23** (incl. footer links).
6. ~~**WhatsApp** chat widget + per-product `wa.me`.~~ — **done 2026-08-23.**
7. ~~**Newsletter subscriber admin UI**.~~ — **done 2026-08-23.**
8. **Invoice PDF**.
9. **Review reply + verified-buyer badge** on storefront.
10. **SMS gateway** (real driver; currently stub).
11. **Ops hardening** — *partly done 2026-08-23*: full suite green (718/718) on a self-provisioning test database, Larastan 0 errors and Pint clean. **Still open:** 41 remaining `phpstan-baseline.neon` suppressions, prod config caching, HTTPS, queue→redis, backups, monitoring, audit-log UI.
12. **Commerce polish (backlog)** — quick-view/zoom/360, infinite scroll, compare/wishlist account persistence, recently-viewed pruning, multi-currency, search engine (Meilisearch), CSV import/export, reorder alerts.
13. **Long-term** — public API / mobile apps / PWA, i18n UI, multi-location shipping, analytics dashboard.

## 7. Confidence note

This audit was performed against the **current working tree** (last commit `e1358d4`; working tree has ~200 modified + ~100 untracked files). Everything is cited from real source; no item is assumed. Where a feature is claimed complete, tests exist (`tests/Feature/**`) or the resource/route is present.

**→ 2026-08-23:** that uncommitted tree is gone — it has been reconciled into logical
commits, so the findings above can now be traced to real history rather than to a
snapshot that only existed on one machine.