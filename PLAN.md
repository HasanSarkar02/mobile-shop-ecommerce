# PLAN — Completing the system (unified master plan)

**Last updated:** 2026-08-23 (working tree reconciled into logical commits; test environment made self-contained. Offers/campaign system, courier engine, admin orders, refunds, trust content verified against code; Phase F — Catalog & PDP Stabilization + Multi-Vertical UX Architecture added, audits not yet started)
**Scope:** commerce correctness + content/trust + **multi-vertical, Bangla, courier-ready** architecture, plus shop-approval governance.
**Companion docs:** `AUDIT.md` (feature inventory) · `ARCHITECTURE-VERTICALS.md` (multi-vertical/Bangla/courier blueprint).
**Legend:** `[ ]` pending · `[~]` in progress · `[x]` done
**Governing rule:** everything vertical-specific is **data/config, never code**; all migrations **additive** — never break existing data.

---

## Phase 0 — Shop approval gate (governance, do first)

Public signup currently grants an instantly-live trial store with a reserved subdomain and **no admin moderation lever** (`TenantResource.php:76-84` has the status select disabled). Fix before opening to real merchants.

1. [x] **Add `pending` tenant status.** Signup creates tenants as `pending` instead of `trial` (`TenantBootstrapService.php`); pending tenants get **no subscription** — the 14-day trial starts fresh on approval. `Tenant::isActive()` (`Tenant.php:62-65`) already excludes `pending`, so `TenantContextResolver.php:177` locks the storefront automatically — no new access logic.
2. [x] **Owner contact phone.** Required on public signup, optional on admin create; stored on `users.phone` (new nullable column, added by `2026_08_21_000001_add_phone_to_users_table.php`), validated as a BD mobile (`app/Rules/BangladeshiPhone.php`, `^01[3-9]\d{8}$`); surfaced in the admin review (owner summary + `ShopNeedsApprovalNotification` admin email). No OTP/SMS in this phase — the admin's phone call is the verification.
3. [x] **Platform admin approve/reject.** Approve/Reject header actions on `ViewTenant` (the status select stays read-only — status is denormalized from subscriptions and `PlatformSafetyBoundaryTest` enforces the invariant); admin notified of new shops via `ShopNeedsApprovalNotification` (mirrors the `PlanChangeRequest` notification pattern).
4. [x] **Subdomain reservation lifecycle.** Reserved while `pending`; released on reject (row renamed to `rejected-{id}`, preserving the audit trail) or auto-expiry via `tenants:release-expired-pending-approvals` (daily, `tenancy.pending_approval_expiry_days` default 7). Existing `trial` tenants remain `trial` (grandfathered) — no data migration needed.
5. [x] **Owner "under review" UX.** Central `/signup/pending` page (both platform domains) + owner emails (`ShopPendingApprovalNotification`) — pending subdomains stay 404-locked.
6. [x] **Tests.** `tests/Feature/Tenancy/*` — signup → pending → lock → approve → live; reject releases subdomain; expiry releases subdomain; invalid phone rejected.

---

## Phase 1 — Commerce correctness (P0)

7. [x] **Payment configuration foundation (shop-owned, COD/manual MFS first).** Additive `payment_methods` columns (`2026_08_22_000001_add_payment_method_configuration_to_payment_methods_table.php`): `code`, `display_name`, `provider`, `account_number/name`, `bank_name/branch_name`, `instructions`, `gateway_mode`, `credentials` `encrypted:array`, `fee_type/value`, `min/max_order_amount`, `requires_verification`, `gateway_ownership=shop`. `PaymentMethodType` `ManualMfs/OnlineGateway` (Aggregator deprecated, `PaymentMethod.php:46` encrypted cast). Filament `PaymentMethodResource.php:34` shop-owned CRUD (owner dashboard, `RestrictsToOwner`), checkout `checkout-page.blade.php:90` instructions + manual card, `ManualPaymentSubmission.php:13` Pending→ verification via `OrderResource.php:346` `verify/reject`, `OrderService.php:347` `recordPayment` idempotency on `tenant_id,transaction_reference`. Fees stored only (calculation deferred). No gateway drivers yet — platform can later add driver + one `config/payment_gateways.php:6` line.
8. [ ] **bKash / Nagad / Online gateway drivers.** Registry at `config/payment_gateways.php:8` — one driver class + one config line (pattern: `app/Services/PaymentGateways/SslcommerzDriver.php`, implemented and live in checkout). bKash/Nagad blocked until API creds arrive — foundation ready.
9. [ ] **Production payments (live creds).** Real SSLCommerz creds in server `.env` (`SSLCOMMERZ_STORE_ID/PASSWORD`, `SSLCOMMERZ_SANDBOX=false`); verify IPN + idempotency. Needs driver above.
10. [x] **Refund workflow.** `OrderStatus::Refunded` live; `OrderService::refund()` (`OrderService.php:585`) + `amountPaid/amountRefunded`; refund header action on `ViewOrder.php:55-63` (amount validated against refundable, method/reason/reference captured); dead `'refunded' => 'gray'` UI now real.
11. [x] **Fix known test failures** (full suite green). Order/checkout failures (`OrderSerialLinkageTest`, `OrderAdminOperationsTest`, `CheckoutConfirmationTest`, `CheckoutDoubleSubmissionTest`, `PurchaseStateTest`) and the `PlatformDashboardTest` 6 (dashboard copy had been redesigned; assertions realigned to `Quick Actions` / `Everything is up to date.` / `Queue Backlog|Scheduler|Database`) are all resolved. **718/718 passing, 2948 assertions, 0 risky** on 2026-08-23, verified from a dropped database. The suite no longer needs manual DB setup — `tests/bootstrap.php` creates the `testing` database — and runs with `failOnRisky`/`failOnWarning` so a zero-assertion test can no longer pass silently (the one offender, `tests/Feature/DebugAccountTest.php`, was a scratch file asserting nothing and has been deleted).
12. [~] **Static analysis + lint** (Pint, Larastan per `pint.json`, `phpstan.neon`). Both gates are green as of 2026-08-23: **Pint reports 0 files needing changes** (the old "397 files" figure was a count of files scanned, not violations) and **Larastan reports 0 errors at level 5** — the earlier "348 errors" are gone. **Remaining:** 41 lines of suppressions still sit in `phpstan-baseline.neon` (down from 329), so the level-5 pass is green *behind a baseline* rather than genuinely clean; retiring the rest is the open work here.
13. [~] **Security housekeeping.** `.env` is gitignored (`.gitignore:3`) and `SSLCOMMERZ_STORE_ID/PASSWORD/SANDBOX` are now in `.env.example`, which also records that courier credentials are deliberately per-shop encrypted rows rather than env vars. **Remaining:** rotate the live SMTP password that was committed in a previous `.env` — a code change cannot undo an exposed credential, this must be done in the mail provider.
14. [x] **Filesystem config sync.** `config/filesystems.php:44` is already `'url' => '/storage'` (host-relative); local and server config agree. No change needed — this item was stale.

---

## Phase 1b — Pre-order payment & split fulfillment (P5, approved & done)

**Locked decisions:** 1) Full upfront only (COD full on delivery, manual/online full via method, no deposit_percent). 2) COD allowed for pre-orders by default, future per-tenant/variant gate stubbed not enforced. 3) Mixed cart = split fulfillment (stock ships now, preorder ships on ETA, not held). 4) ETA required + future (`VariantsRelationManager.php:80` `required|after:now` + `ProductVariant.php:59` `booted` domain invariant). 5) Guest pre-order allowed (no login gate, keeps `CheckoutPage.php:100` guest branch + rate limit).

15. [x] **P5.1 Admin hardening.** Filament `VariantsRelationManager.php:80` `expected_available_at` required/after:now + helperText, `text-purple-600` distinct from low-stock amber, product-list indicator. Domain invariant in `ProductVariant::booted` (also covers non-Filament saves). No `+21d` backfill — legacy NULLs stay, validation only on save.
16. [x] **P5.2 /pre-order discovery.** `GET /pre-order` (`routes/tenant.php:43` `PreorderController.php:10` `Product::published()->whereHas variants preorder`), `index.blade.php`, product-card ETA badge, nav link pending.
17. [x] **P5.3 Order snapshot.** Additive `order_items` (`2026_08_22_000002`: `fulfillment_strategy nullable`, `expected_available_at nullable`, `order_fulfillment_id nullable FK`), `orders.preorder_ack_at nullable` + `order_fulfillments.fulfillment_group default stock` + `expected_available_at` (`2026_08_22_000003`), models `OrderItem.php:16`, `Order.php:32`, `OrderFulfillment.php:16` + `items()` relation. `OrderService::createFromCart` snapshots variant strategy/ETA.
18. [x] **P5.4 Cart/checkout pre-order UX + PDP CTA.** Cart `cart-page.blade.php:22` PRE-ORDER badge + ETA, checkout `checkout-page.blade.php:150` purple banner + mixed banner + `preorder_ack` checkbox → `orders.preorder_ack_at`, summary grouped with per-line ETA, PDP `show.blade.php:379` Buy Now consistent `Pre-Order Now`, `availabilityTone:1012` purple.
19. [x] **P5.5 Mixed-cart split fulfillment.** `OrderService.php:211` partition by `fulfillment_strategy` (stock|preorder|dropship future extensible), one fulfillment per strategy with `expected_available_at = earliest preorder ETA`, `OrderItem.order_fulfillment_id` populated, single-strategy stays 1 row (backward compat for `OrderServiceTest:44` `toHaveCount(1)`), `OrderResource.php:236` fulfillment repeatable + `ViewOrder.php:48` `fulfillment_id` selector, storefront `confirmation/track-result/account/show` use snapshot not live variant.
20. [x] **Tests.** `tests/Feature/PreorderFulfillmentTest.php:1` 10 tests (stock single, mixed split 2 rows + ETA min + item link, all-preorder single, ack persisted, historical NULL valid, guest allowed, full-upfront payment) — all passing. Historical `NULL` snapshots remain valid as stock.

---

## Phase 1c — Admin order creation (owner panel) — DONE

21. [x] **Admin Create Order (Filament Store, cartless).** `OrderService::createFromAdmin()` (`OrderService.php:295`, `DatabaseLockRetry` + stock locks reused, `order_source` snapshot), Filament `OrderResource/Pages/CreateOrder.php` (customer select, lines repeater, addresses, payment/shipping, preorder ack auto) reachable from `ListOrders`. Pending-only mutators untouched.

---

## Phase 1d — Pluggable courier engine (Steadfast + Pathao, platform registry + shop credentials, one-click) — D.1–D.3 DONE

**Honest audit of proposal:** Your "platform registers base*url, shop connects via API key — new courier without code" is 90% true. A DB-registered provider row (base_url_sandbox/live, auth_type, required_fields JSON, driver_class) lets non-dev add metadata without deploy, but a \_new* provider with different API shape still needs a driver class (`SteadfastDriver`, `PathaoDriver`) — you cannot generalize away provider differences. Hardcoding base URLs in drivers is the anti-pattern you flagged; DB registry fixes it. Copy `config/payment_gateways.php:6` + `PaymentMethod.php:46` encrypted pattern exactly.

**Steadfast API (portal.packzy.com/api/v1):** `Api-Key/Secret-Key` headers, `POST /create_order` (`invoice` unique, `recipient_name/phone 11 digits`, `recipient_address 250c`, `cod_amount`, `delivery_type 0/1`), `POST /create_order/bulk-order` data JSON 500 max, `GET /status_by_cid|invoice|trackingcode`, `GET /get_balance`, `GET /police_stations`, `POST /create_return_request`. Statuses `pending/in_review/delivered/...`.

**Pathao API (courier-api-sandbox.pathao.com):** OAuth `POST /aladdin/api/v1/issue-token` (`client_id/secret + grant_type password|refresh_token + username/password` → `access_token 432000s`), `POST /stores` (`city_id/zone_id/area_id`), `POST /orders` (`store_id, merchant_order_id, recipient_*, delivery_type 48|12, item_type 1|2, item_weight 0.5-10kg, amount_to_collect`), `GET /city-list|zone-list|area-list`, `POST /merchant/price-plan`.

22. [x] **Phase D.1 — Platform registry.** `create_courier_providers_table` (`2026_08_22_000004`, central): `code unique`, display/base URLs sandbox+live, `auth_type`, `required_fields JSON`, `driver_class`, `is_active`, sort. Platform Filament resource for provider rows.
23. [x] **Phase D.2 — Shop connection (owner dashboard, encrypted).** `create_courier_connections_table` (`2026_08_22_000005`, tenant-scoped): encrypted `credentials:array`, `is_active/is_default/sandbox/sort`, unique `(tenant,courier_provider)`; Filament Store `CourierConnectionResource` with dynamic fields from `required_fields`, sandbox toggle.
24. [x] **Phase D.3 — One-click & bulk shipment (live).** `CourierDriver` interface (`app/Services/Shipping/CourierDriver.php`: `createShipment/createBulk/fetchStatus/fetchBalance`) + `SteadfastDriver`/`PathaoDriver`; `CourierService::sendFulfillment/syncStatus`; `ViewOrder.php:130` Send-to-Courier action (connection + fulfillment select, COD = grand_total − paid); bulk send on `ListOrders` (`OrderResource.php:65` → driver `createBulk`, per-invoice result notifications).
25. [~] **Phase D.4 — Status sync + timeline.** Manual status sync action live on `ViewOrder` (`CourierService::syncStatus` → fulfillment status + timeline). Automated cron now shipped: `tenants:refresh-courier-status` (`app/Console/Commands/RefreshCourierStatus.php`) walks trial + active tenants, polls only in-flight fulfillments that carry a tracking number, resolves the tenant's connection by the courier name recorded at shipment time (zero or ambiguous matches are logged and skipped, never guessed), isolates per-consignment failures so one provider outage cannot abort the run, and always clears tenant context in a `finally`. Scheduled hourly `withoutOverlapping` (`routes/console.php`), covered by `tests/Feature/Console/RefreshCourierStatusTest.php`. **Remaining:** add `->onOneServer()` to the schedule before multi-server deploy (every other schedule entry has it); verify storefront surfacing on `track-order/result` + account pages; webhook endpoint deferred until providers offer them.

---

## Phase 2 — Content & discovery

26. [x] **Offers landing page.** Done and expanded well beyond the original line — see "Offer module" subsection below.
27. [x] **Newsletter admin UI.** Filament `NewsletterSubscriberResource` (list, delete + bulk delete, per-view and full CSV export); storefront subscribe form + throttled `NewsletterController` were already live.
28. [ ] **Review reply + verified-buyer badge** on PDP; keep moderation (`ReviewStatus`), pre-order ETA now consistent.
29. [ ] **Invoice PDF** for orders (receipt `OrderReceiptController.php:72` HTML exists — add downloadable PDF via `barryvdh/laravel-dompdf`).

### Offer module (2026-08-22, beyond original #26)

- [x] **Campaign ↔ product system.** `campaign_product` pivot (sort_order) + `Campaign::products()`; single-source eligibility `Campaign::scopeEligible()/isEligible()`; shared `CampaignProductResolver` (publication + pivot order + batched max-discount); homepage grid source `'campaign'` wired in `HomepageSectionRenderer`; Filament products attach on `CampaignResource`.
- [x] **Storefront pages.** `/offers` index (brand-gradient hero, sortable Newest/Ending Soon, colorful offer cards 1/2/3-col with accent_color + preset tint fallbacks, short_tagline, date-range pills, "Up to X% OFF" computed from compare-at prices, trust-badge strip, empty-state) + `/offer/{slug}` show (hero w/ artwork, segmented D-H-M-S Alpine countdown in `partials/offers/countdown.blade.php`, copyable coupon row via `HasSchedule::currentlyActive`, campaign product grid reusing `product-card`, CTA to on-page deals). Fixed latent bug: `/offer/{slug}` always 404'd (route-param/binding mismatch).
- [x] **Campaign imagery.** Nullable `hero_image`/`card_image` columns + Filament uploads (`FileUpload->directory('campaign-heroes'/'campaign-cards')`, image validation, size limits, previews); predictable fallbacks — card: card_image → hero_image → banner WebP ('large') → tint+icon; hero: hero_image → banner → generated placeholder. Mobile-first hero (artwork stacks under content), 16:9 card band (object-cover uploads / object-contain banners), reserved aspect ratios, lazy/eager loading split.
- [x] **Tests.** `tests/Feature/Storefront/OfferPageTest.php` (5) + `OfferCampaignProductsTest.php` (12): eligibility, draft-product exclusion, pivot ordering, both sorts, discount calc + no-false-discount, coupon validity gating, homepage source eligibility, image rendering + fallbacks.

---

## Phase 3 — Trust & support

30. [x] **Outlets / store locator.** `Outlet` model + `create_outlets_table` (`2026_08_22_100002`), `/outlets` index (`OutletController.php`, active + sort), Filament `OutletResource`, `OutletPageTest`. Footer wiring is done too: `StorefrontLayoutComposer` passes `hasOutlets` and `components/storefront/footer.blade.php:68-71` renders the locations link only when the shop actually has outlets.
31. [x] **Policy & help pages.** `TrustContentSeeder` seeds all 8 policy pages (delivery, warranty, return, exchange, refund, privacy, EMI/payment, pre-order) + general FAQ entries per tenant; idempotent (safe on existing tenants); PDP policy strip + footer resolve against these slugs.
32. [x] **WhatsApp widget.** `whatsapp_widget_enabled` toggle (`2026_08_22_100001` + `ThemeSettings.php`) drives floating widget (`partials/whatsapp-widget.blade.php`); PDP "Ask about this product on WhatsApp" uses `social_links.whatsapp` via `App\Support\WhatsApp::url()`; tests in `WhatsAppWidgetTest`.

---

## Backlog

33. [ ] **Commerce polish (backlog picks).** Quick-view / gallery zoom; infinite scroll on listing; compare/wishlist account persistence; recently-viewed pruning + widget.
34. [ ] **Ops hardening.** Prod config caching, HTTPS enforcement, queue driver review, backups, monitoring/rate-limit review.

---

## Phase F — Catalog & PDP Stabilization + Multi-Vertical UX Architecture

**Governing rule (unchanged):** vertical-specific behavior is data/configuration and shared components — never duplicated application code or per-vertical page implementations.
**Order of work:** read-only audits first; NO immediate product-card or PDP redesign. Bugs found are triaged into "fix now" vs "defer to foundation".

57. [~] **F.0 — Read-only Catalog audit.** ~~Brand-facet bug (selecting Apple hides other brands/facets)~~ — **already fixed**: `FacetResolver::resolve()` now takes a `?\Closure $candidatesFor` so each dimension counts against a query with its *own* filter excluded, and attribute counts are de-duplicated per product. Proven by `tests/Feature/Storefront/CatalogFixesTest.php` ("keeps other brands visible in facets when one brand is selected", "keeps unselected attribute options visible…", "still respects other dimensions while excluding a facet own dimension"); price-input hardening covered too ("ignores negative zero and non numeric price inputs"). **Still to audit:** category filtering, sorting, pagination, query/filter state persistence, filter reset, desktop filter UX, mobile filter UX/drawer, empty states, loading states.
58. [~] **F.1 — Read-only Product Card audit.** ~~Wishlist broken on cards~~ — **already fixed and covered**: `tests/Feature/Storefront/ProductCardWishlistTest.php` (16 tests: SSR wishlist seeding on wishlist + collection pages, JSON toggle state, non-JSON redirect/flash, unknown product rejected, cross-tenant leakage, no per-card N+1) and `ProductCardCtaTest.php` (11 tests: Add-to-Cart / Pre-Order / Backorder / disabled / no-CTA / Select-Options resolution). Card variant/stock/pre-order state and badge/pricing selection now run through the shared `PurchasabilityPolicy` + `ProductCardData`. **Still to audit:** image handling, responsive behavior, accessibility, loading/error states.
59. [ ] **F.2 — Read-only PDP audit.** Variant selection correctness, gallery, wishlist, Add to Cart, Buy Now, stock/pre-order behavior, pricing display, responsive/mobile UX, related products rails, reviews, trust information.
60. [ ] **F.3 — Read-only Product/Variant/Attribute architecture audit.** EAV attribute system coverage vs deprecated phone-first variant columns; whether existing variant data can express storage/RAM/color, size/color, pack/unit, material/color/dimensions without schema duplication.
61. [ ] **F.4 — Define shared UI primitives** (single implementations): `ProductImage`, `ProductPrice`, `ProductRating`, `WishlistButton`, `VariantSelector`, `AddToCartButton`, `DiscountBadge`, `StockBadge`, `Gallery` — extracted from current partials, not forked.
62. [ ] **F.5 — Define industry composition/config presets** (`config/industries.php` extension): Electronics/Mobile, Fashion, Grocery, Sports, Furniture, General fallback. Industry controls: component composition, information priority, attribute presentation, card layout, PDP layout, gallery behavior, CTA behavior, design tokens.
63. [ ] **F.6 — Card hover-preview gallery decision (audit + configure, never auto-enable).** Desktop-only: hovering a product card with multiple images previews/advances through the gallery; mouse-leave returns to the primary image; touch devices never depend on hover. Per-industry default: fashion = strongly useful · electronics/mobile = useful · furniture = useful · sports = optional · grocery = usually unnecessary. Final toggle resolved per storefront/industry preset during F.5.
64. [ ] **F.7 — Card Add-to-Cart / variant-modal behavior.** One shared variant-selection engine (the PDP's), never two implementations.
    - Product has selectable variants → Card "Add to Cart" opens Variant Selection Modal → valid combination chosen → add to cart.
    - No selection required → direct add-to-cart from card.
    Modal must express any attribute type so future verticals work without new code: mobile (storage/RAM/color), clothing (size/color), grocery (pack/unit where applicable), furniture (material/color/dimensions).
65. [ ] **F.8 — Triage + roadmap write-back.** Split audit findings into (a) bugs that must be fixed immediately vs (b) redesign work deferred to the Multi-Vertical Foundation; record the split in this PLAN.md before any implementation begins.

---

## Phase A — i18n + Bangla foundation

**Decision:** English = root URL default; Bangla optional via `/bn/`; each tenant enables its own language set + preferred locale. Western numerals (matches Chaldal/Daraz).

35. [ ] **Translation files.** `lang/en.json`, `lang/bn.json`; sweep all hardcoded storefront strings → `__()`.
36. [ ] **Locale middleware** `SetLocale`: tenant enabled-locales → URL prefix (`/bn/`) → browser → persisted user pref → tenant preferred locale; `App::setLocale()`.
37. [ ] **Tenant locale columns.** `locales` (array) + `preferred_locale`; signup/admin UI to configure.
38. [ ] **Serve stored translations.** Replace hardcoded `translation('en')` with `translation($locale)` at `Product.php:60`, `ProductCardData.php:97,202`, `ProductController.php:105,218`, `show.blade.php:3-91`; make sort joins + Scout search locale-aware (`ProductListingService.php:128`, `Product::toSearchableArray` `Product.php:155-165`).
39. [ ] **Bangla font** self-hosted with Bengali unicode-range (Hind Siliguri / Noto Sans Bengali) in Vite build; **keep Western numerals**.
40. [ ] **Centralize money.** `money()` helper (locale-aware `Number::format`, symbol via `app/helpers.php:25-36`); remove ~30 hardcoded `৳` sites.

---

## Phase B — Vertical abstraction + design system

**Decision:** owner selects an industry at signup → seeded starter structure (categories, attributes, templates, homepage) → fully customizable later.

41. [ ] **`Tenant.industry`** enum (`electronics/mobile/fashion/grocery/sports/general`) + `config/industries.php` presets seeding: category-tree template, attribute presets (grocery: unit/weight/pack-size; fashion: size/color), homepage template, nav style, theme preset.
42. [ ] **Design tokens.** `--color-*` scale in `@theme` (`resources/css/app.css:10-18`); wire dead `secondary_color` + `font_family` (`ThemeSettings.php:47-48`) to real CSS vars.
43. [ ] **Per-vertical theme presets** (grocery=green/friendly, electronics=blue/spec-led, fashion=minimal/large-imagery); owner overrides anytime.
44. [ ] **De-brand structural surfaces.** Footer/header to neutral surfaces with brand accents (`footer.blade.php:5`, `desktop-header.blade.php:138`); add layout/header style options.
45. [x] **Fix homepage reorder.** `HomeController.php:16` now has `->orderBy('sort_order')`, so Filament's reorder actually drives homepage section order. Covered by `tests/Feature/Storefront/HomepageSectionSortingTest.php`.

---

## Phase C — Grocery/fashion data model

**Decision (confirmed):** deprecate, **don't drop** the phone-first variant columns — no destructive migration.

46. [ ] **`unit_of_measures` entity** (kg, g, l, ml, pcs, dozen, pack) + per-product `sell_by_unit` + **per-unit pricing** + conversion service.
47. [ ] **Measured/loose stock.** `StockItem` gains decimal quantity + uom for loose goods; keep integer fast-path (`StockItem.php:15,40-43`, `InventoryService.php`).
48. [ ] **De-phone schema (deprecate, don't drop).** Mark `color/storage_gb/ram_gb/sim_type/region` variant columns (`add_specs_pricing_media_to_product_variants_table.php:13-18`) as deprecated/no-op; add EAV-backed equivalents; **no data migration, no column removal**. `ProductController.php:119-131` reads move to attribute-driven with fallback to deprecated columns.
49. [ ] **Bulk variant generator** (cartesian) — fashion store creates 36 SKUs in one click (`VariantsRelationManager.php:109-110`).

---

## Phase D — Courier architecture (remaining)

**Decision:** Pathao + Steadfast first; interface stays provider-agnostic — now concrete via Phases D.1-D.4 above.

50. [ ] **BD address hierarchy** (division → district → thana → area) as tables; guest checkout captures `area` (`CheckoutPage.php:28` currently omits it); shipping-zone linkage.
51. [ ] **`ShippingService` quote engine.** weight + zone + order-value + COD-amount → dynamic fee at checkout (replaces `CheckoutPage.php:88` flat copy); free-shipping threshold; pickup points tied to `Location` (`Location.php:17`). Can reuse `mock` drivers price-plan (`Pathao POST /merchant/price-plan`) before live booking.
52. [ ] **Order persistence (remaining).** Snapshot `weight_grams` + `delivery_zone` + `cod_amount` on `orders`/`order_items`; automatic COD `OrderPayment` at placement (`PaymentMethodType::Cod`) — P5 already snapshots `fulfillment_strategy/expected_available_at` + `fulfillment_group`; extend for weight/zone.

---

## Phase E — Vertical-native storefront patterns (config-driven)

53. [ ] **Grocery preset UI.** Category-tile-dominant home, "quick-add +" cards, city/area selector bar, delivery-fee-by-area display, unit/pack-size facets.
54. [ ] **Fashion preset UI.** Large-imagery cards, size/color facet-first, minimal chrome.
55. [ ] **Electronics preset UI.** Spec-led PDP + facet rail (mostly exists — make locale-aware).
    55.1. [ ] **Furniture preset UI.**
56. [ ] **Fallback to `general`** preset for anything unmapped; all via config + Blade components — no per-vertical codebases.

---

## Sequencing

- **Done (earlier revision):** Phase 1c (admin order creation), Phase 1d D.1–D.3 (courier engine), refunds (#10), offers/campaign module + campaign imagery (#26), newsletter admin (#27), outlets core (#30), policy/FAQ seeding (#31), WhatsApp widget (#32).
- **Done (2026-08-23):** courier status-sync cron (#25 cron part) · outlet footer links (#30 complete) · homepage reorder (#45) · full suite green at 718/718 with a self-provisioning test database (#11) · Pint + Larastan gates green (#12, baseline residue aside) · `.env.example` gateway keys (#13, SMTP rotation still outstanding) · filesystem config confirmed already correct (#14).
- **Next — small polish batch:** `->onOneServer()` on the courier schedule before multi-server deploy · retire the remaining 41 baseline suppressions (#12) · rotate the exposed SMTP password (#13) · add real coverage for customer login + `/account` (currently **zero** tests — the only file that touched those routes was a scratch debug test that asserted nothing and has been deleted).
- **Then:** Phase 2 leftovers (#28 review replies, #29 invoice PDF) and Phase A (Bangla) + Phase B (verticals/design tokens) in parallel.
- **After:** Phase C (grocery model) → Phase D remaining (BD hierarchy, ShippingService quote) → Phase E (per-vertical UI).
- **Before Phase A/B UI work:** run Phase F (F.0–F.3 read-only audits → F.4/F.5 primitives & presets definition) so catalog/PDP fixes and vertical theming land once, on shared components, instead of twice. F.0/F.1 are partly discharged — the specific brand-facet and card-wishlist bugs are fixed and tested; the UX/accessibility halves of those audits are still open.

## Deferred (later)

- Real gateway drivers (bKash/Nagad live — foundation ready in Phase 1 #7-8).
- Subscription auto-renewal / dunning / plan-upgrade end-to-end.
- Search engine upgrade (Scout database → Meilisearch/Algolia), filters-in-URL.
- Multi-currency engine; CSV import/export; reorder alerts; admin audit-log UI.
- Public REST API / PWA / mobile apps; RTL (only if Arabic added — Bangla is LTR); multi-location shipping; analytics dashboard.
- Demo content seeder (categories, products, images, reviews).

## Verification per task

- Each behavior task ships with or updates a `tests/Feature/**` test; `composer test` stays green.
- Storefront verified against `applegadgetsbd.com`, `gadgetandgear.com` (electronics), `chaldal.com`, `daraz.com.bd` (grocery/multi-vertical + Bangla) reference behavior.
