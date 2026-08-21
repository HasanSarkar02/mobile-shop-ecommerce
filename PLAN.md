# PLAN — Completing the system (unified master plan)

**Last updated:** 2026-08-22
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
8. [ ] **bKash / Nagad / Online gateway drivers.** Registry at `config/payment_gateways.php:8` — one driver class + one config line (pattern: `app/Services/PaymentGateways/SslcommerzDriver.php`). Blocked until API creds arrive — foundation ready.
9. [ ] **Production payments (live creds).** Real SSLCommerz creds in server `.env` (`SSLCOMMERZ_STORE_ID/PASSWORD`, `SSLCOMMERZ_SANDBOX=false`); verify IPN + idempotency. Needs driver above.
10. [ ] **Refund workflow.** Add `OrderStatus::Refunded`; refund action on `OrderResource`; wire `OrderService` refund path; remove dead `'refunded' => 'gray'` UI (`OrderResource.php:815`).
11. [ ] **Fix known test failures** (full suite green): `OrderSerialLinkageTest.php:72`; `OrderAdminOperationsTest.php:241,313`; `CheckoutConfirmationTest` (3, routing/tenancy 404); `CheckoutDoubleSubmissionTest` (tenant_id 1364); `PurchaseStateTest` 10× 404 (pre-existing, tenant factory scoping).
12. [ ] **Static analysis + lint** (Pint, Larastan per `pint.json`, `phpstan.neon`); address findings (Pint 397 files; Larastan 348 errors at level 5 — mostly Filament generics + dead refund UI).
13. [ ] **Security housekeeping.** Rotate live SMTP password in `.env:51-58`; ensure `.env` stays gitignored; add `SSLCOMMERZ_*` (and new gateway vars) to `.env.example`.
14. [ ] **Filesystem config sync.** Local `config/filesystems.php:44` still uses `rtrim(env('APP_URL'))` — align with server fix (`'/storage'` host-relative) and commit.

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

## Phase 1c — Admin order creation (owner panel, next)

**Gap:** `OrderResource.php:36` is view-only (no `form()`, only `index|view` `830`, `ListOrders.php:13` no CreateAction). `OrderService.php:43` only `createFromCart(Cart)`, but `OrderSource.php:10` already has `Admin='admin'` unused. Admin cannot originate an order — must go storefront→cart.

21. [ ] **Admin Create Order (Filament Store, cartless).** New `OrderService::createFromAdmin(array variantId=>qty, Customer|guest, addresses, payment/shipping, preorder_ack_at, source Admin)` reusing `createFromCart` locks (`variantIds sort lockForUpdate:121`, `lockStockItemsForVariants`, `isPurchasable:174`, `reserve:224`, `recalculateTotals`, split fulfillments `211`), bypass `Cart`/`converted_at`/`ReservationLimitExceededException` (`active_reservation_key` admin bypass or null). Filament `OrderResource/Pages/CreateOrder` (`ListOrders` header `CreateAction`) with customer selector (search `Customer` or guest fields `guest_name/email/phone`), repeater `variant_id search `variantOptions:714` + quantity, address snapshot, payment/shipping, preorder_ack auto, `OrderSource::Admin`. Keep all `Pending`-only mutators (`addItem:518`, etc.) untouched. Enterprise: `DB::transaction` + `lockForUpdate` deterministic order, `OrderPlaced` dispatch, `OrderEvent` audit.

---

## Phase 1d — Pluggable courier engine (Steadfast + Pathao, platform registry + shop credentials, one-click)

**Honest audit of proposal:** Your “platform registers base_url, shop connects via API key — new courier without code” is 90% true. A DB-registered provider row (base_url_sandbox/live, auth_type, required_fields JSON, driver_class) lets non-dev add metadata without deploy, but a *new* provider with different API shape still needs a driver class (`SteadfastDriver`, `PathaoDriver`) — you cannot generalize away provider differences. Hardcoding base URLs in drivers is the anti-pattern you flagged; DB registry fixes it. Copy `config/payment_gateways.php:6` + `PaymentMethod.php:46` encrypted pattern exactly.

**Steadfast API (portal.packzy.com/api/v1):** `Api-Key/Secret-Key` headers, `POST /create_order` (`invoice` unique, `recipient_name/phone 11 digits`, `recipient_address 250c`, `cod_amount`, `delivery_type 0/1`), `POST /create_order/bulk-order` data JSON 500 max, `GET /status_by_cid|invoice|trackingcode`, `GET /get_balance`, `GET /police_stations`, `POST /create_return_request`. Statuses `pending/in_review/delivered/...`.

**Pathao API (courier-api-sandbox.pathao.com):** OAuth `POST /aladdin/api/v1/issue-token` (`client_id/secret + grant_type password|refresh_token + username/password` → `access_token 432000s`), `POST /stores` (`city_id/zone_id/area_id`), `POST /orders` (`store_id, merchant_order_id, recipient_*, delivery_type 48|12, item_type 1|2, item_weight 0.5-10kg, amount_to_collect`), `GET /city-list|zone-list|area-list`, `POST /merchant/price-plan`.

22. [ ] **Phase D.1 — Platform registry.** Migration `create_courier_providers` **without** `BelongsToTenant` (central): `code unique` (`steadfast|pathao`), `name`, `display_name`, `base_url`, `base_url_sandbox`, `base_url_live`, `auth_type` (`api_key|oauth`), `required_fields JSON` (`["api_key","secret_key"]` vs `["client_id","client_secret","username","password"]`), `driver_class` (FQCN), `is_active`, `sort_order`. `config/couriers.php` drivers map (`steadfast=>SteadfastDriver`, `pathao=>PathaoDriver`) — one line per driver future. Platform Filament `CourierProviderResource` (`/platform`, `EnsureCentralDomain`, `is_platform_admin` like `PlatformPanelProvider:28`).
23. [ ] **Phase D.2 — Shop connection (owner dashboard, encrypted).** Migration `create_courier_connections` with `BelongsToTenant.php:12` (`tenant_id` auto + scoped): `tenant_id FK`, `courier_provider_id FK`, `credentials encrypted:array` (`Steadfast: api_key/secret_key`, `Pathao: client_id/secret + username/password + access_token/refresh_token/expires_at/store_id`), `is_active`, `is_default`, `sandbox bool`, `sort_order`, unique `(tenant_id,courier_provider_id)`. Filament Store `CourierConnectionResource` (`RestrictsToOwner:PaymentMethodResource:8`, options `array_keys(config('couriers.drivers'))` → provider Select, dynamic fields from `required_fields`, sandbox Toggle, Test Connection button (`GET /get_balance` / `GET /city-list`)).
24. [ ] **Phase D.3 — One-click & bulk shipment (live).** Interface `CourierDriver { createShipment(Order, OrderFulfillment, credentials): ShipmentResult; createBulk(array, credentials): BulkResult; fetchStatus(tracking, credentials): ShipmentStatus; fetchBalance(credentials): float }`. Drivers `SteadfastDriver` (`Http::withHeaders Api-Key/Secret-Key`, base from provider row), `PathaoDriver` (token issue/refresh + `Authorization: Bearer`, store_id lookup `GET /stores`). `ViewOrder.php:48` header action `Send to Courier` — Select `courier_connection` (active), Select `fulfillment` if `count>1` (like split logic), `cod_amount = grand_total - amountPaid` (preorder full-upfront already paid → 0), map `recipient_*` from `shipping_address_snapshot` (`Order.php:52`), `invoice=order_number` idempotency, bulk via `ListOrders` bulk action (Steadfast 500 limit). Persist `fulfillment.tracking_number/courier_name/consignment_id` + `expected_available_at`, log `FulfillmentUpdated:OrderEventType:11` + `OrderEvent`. Handle `invoice unique` bulk per-item error array.
25. [ ] **Phase D.4 — Status sync + timeline.** Unified `ShipmentStatus` `pending/in_review/delivered/...` mapped from Steadfast + Pathao, cron `tenants:refresh-courier-status` `GET /status_by_*`, update `OrderFulfillment.status` (`Pending|Packed|Shipped|Delivered|Failed:OrderFulfillmentStatus:7`), surface in `track-order/result:32` + `account/orders/show:21` (already loops all fulfillments), add webhook endpoint `track-order/result.blade.php:23-43` slot later if provider webhooks arrive.

---

## Phase 2 — Content & discovery

26. [ ] **Offers landing page.** `/offer` grid + `/offer/{slug}` using existing `Campaign` (`starts_at/ends_at`, status); live countdown (Alpine); seed demo campaigns.
27. [ ] **Newsletter admin UI.** Filament resource for `NewsletterSubscriber` (list/export/remove) + wire CTA (storefront subscribe + throttled `NewsletterController.php:55` already exists).
28. [ ] **Review reply + verified-buyer badge** on PDP; keep moderation (`ReviewStatus`), pre-order ETA now consistent.
29. [ ] **Invoice PDF** for orders (receipt `OrderReceiptController.php:72` HTML exists — add downloadable PDF via `barryvdh/laravel-dompdf`).

---

## Phase 3 — Trust & support

30. [ ] **Outlets / store locator.** `Outlet` model + `/outlet/{slug}` pages + footer locations (mirror AG).
31. [ ] **Policy & help pages.** Seed static-page system with EMI, warranty, exchange, refund, return, delivery, privacy, pre-order policies; FAQ entries.
32. [ ] **WhatsApp widget.** Settings-driven floating chat + per-product `wa.me` (AG/GG parity, `ThemeSettings.php:51` `social_links.whatsapp` already stored).

---

## Backlog

33. [ ] **Commerce polish (backlog picks).** Quick-view / gallery zoom; infinite scroll on listing; compare/wishlist account persistence; recently-viewed pruning + widget.
34. [ ] **Ops hardening.** Prod config caching, HTTPS enforcement, queue driver review, backups, monitoring/rate-limit review.

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
45. [ ] **Fix homepage reorder.** `orderBy('sort_order')` in `HomeController.php:14-16` (currently broken — Filament reorder is decorative).

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
56. [ ] **Fallback to `general`** preset for anything unmapped; all via config + Blade components — no per-vertical codebases.

---

## Sequencing
- **Now:** Phase 1c (admin order creation) → Phase 1d (pluggable courier: platform registry + shop credentials + one-click shipment, Steadfast/Pathao).
- **Parallel after P1c/d:** Phase 1 (refund, test green, lint, security) with Phase 2 + 3 (content/trust) and Phase A (Bangla) + Phase B (verticals) — per `PLAN:120` parallel rule.
- **Then:** Phase C (grocery model) → Phase D remaining (BD hierarchy, ShippingService quote) → Phase E (per-vertical UI).

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
