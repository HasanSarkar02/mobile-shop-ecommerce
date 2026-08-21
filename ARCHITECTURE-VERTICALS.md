# ARCHITECTURE — Multi-Vertical + Bangla + Courier (Future-Proof Blueprint)

**Date:** 2026-08-21
**Status:** Approved plan — awaiting go-ahead for implementation (nothing here is built yet)
**Companion docs:** `AUDIT.md` (feature inventory) · `PLAN.md` (Phases 1–3, commerce correctness)

---

## 1. Vision & governing principles

Our system must serve **many business verticals on one platform**: electronics, mobile phones, clothing/fashion, sports, groceries, and more — with a storefront UI that feels *native* to each industry, a fully **Bangla-capable** experience for Bangladesh, and a **courier-API-ready** delivery layer.

**Golden rule: everything vertical-specific is data/config, never code.**
A new business type = an admin seeds an "industry preset". No schema churn, no per-vertical codebases, no forked templates. This is what makes the platform serve for years without major architectural change.

Secondary rules:
- **Additive migrations only.** Never break existing electronics/mobile data.
- **Everything tenant-scoped** via `BelongsToTenant` (already enforced).
- **Driver-registry pattern everywhere** (proven by `config/payment_gateways.php:5-9`): one interface, one config line per provider.
- **Store owners must feel wanted at a glance** — distinct, polished, industry-appropriate identity out of the box, fully customizable after.

---

## 2. Current-state verdict (honest)

### Genuinely flexible today (foundation to build on)
| Asset | Evidence |
|---|---|
| Typed EAV attributes (text/number/decimal/boolean/select/multiselect), tenant-scoped + category-scoped | `app/Models/AttributeDefinition.php:17-27`; `app/Enums/AttributeDataType.php:7-26`; `category_attribute_definition` pivot |
| Hierarchical categories, unlimited depth | `app/Models/Category.php:27-35` (self-referencing `parent_id`) |
| Metadata-driven facets (no vertical hardcoding) | `app/Services/Storefront/FacetResolver.php:39-58` |
| Unlimited media galleries, per-variant imagery | `app/Models/Product.php:138-141`; `app/Models/ProductVariant.php:120-123` |
| Generic variant-combination integrity engine | `app/Services/VariantSignatureService.php` |
| Mobile-first, strong a11y, real UI kit (15 components) | `resources/views/components/ui/*` |
| Courier/tracking manual persistence columns | `order_fulfillments.courier_name`/`tracking_number` |

### 4 hard blockers for the grocery-in-Bangladesh vision
1. **No Bangla at all.** Zero `lang/` dir, zero `__()/trans()` in storefront, no locale middleware (`bootstrap/app.php:46-56` registers only tenancy), stored `bn` translations never served (hardcoded `translation('en')` at `app/Models/Product.php:58-61`, `resources/views/storefront/products/show.blade.php:3`). Latin-only font (Instrument Sans has no Bengali unicode-range).
2. **Phone-first rigidity in shared tables.** Native variant columns `color/storage_gb/ram_gb/sim_type/region` (`database/migrations/2026_07_09_095937_add_specs_pricing_media_to_product_variants_table.php:13-18`, `2026_07_15_100708_add_region...php:13-16`) — electronics legacy in the core schema.
3. **No grocery commerce model.** No units-of-measure entity, no per-unit pricing (৳120/kg), integer-only stock (12.5 kg impossible), no wholesale tiers. `weight_grams/length_mm/width_mm/height_mm` (`app/Models/ProductVariant.php:36`) stored but **never consumed** by any logic.
4. **Flat-rate shipping only; zero courier infrastructure.** `app/Enums/ShippingMethodType.php:9-11` = `flat_rate|free|pickup`; checkout copies `ShippingMethod.cost` verbatim (`app/Livewire/CheckoutPage.php:88,161`). No BD address hierarchy (division/district/thana), no COD collect-amount, no courier driver layer, no webhook/status ingestion.

### 4 "store-owner-feel" blockers
1. **Theming shallow; 2 options dead.** `secondary_color` + `font_family` stored but never rendered (`app/Filament/Store/Pages/ThemeSettings.php:47-48`). Brand color hardcoded into structural areas — whole footer `bg-[var(--brand)]` (`resources/views/components/storefront/footer.blade.php:5`), header bar (`desktop-header.blade.php:138`). Two different businesses look near-identical.
2. **No vertical/industry concept.** `app/Models/Tenant.php:17-20` has no `industry` — nothing drives a grocery vs fashion vs electronics identity.
3. **Homepage section reorder is broken.** `app/Http/Controllers/Storefront/HomeController.php:14-16` ignores `sort_order`; Filament drag-reorder (`HomepageSectionResource.php:147`) is decorative on the storefront.
4. **৳ + `number_format()` hardcoded ~30 places** (PDP, cart, checkout, orders, filters, `components/ui/price.blade.php`, `CompareController.php:133`); not locale-aware.

---

## 3. Market patterns adopted (reference scan)

| Pattern | Source | Requirement it implies |
|---|---|---|
| EN/বাং language toggle, persisted per user | Chaldal, Daraz | URL-prefix i18n + per-tenant enabled locales |
| City/area selector + delivery-fee table by city | Chaldal | BD address hierarchy + zone shipping |
| Flash-sale / deal countdown with live timer | Daraz, AppleGadgets | Public offer pages on existing `Campaign` (`starts_at/ends_at`) |
| "Pay after receiving" / COD-first messaging + payment badges (bKash/Nagad/Rocket) | Chaldal, Daraz | COD collect-amount + courier linkage |
| Category-dominant grocery home vs hero-driven electronics home | Chaldal vs AppleGadgets | Per-vertical layout presets |
| EMI, trust badges, WhatsApp, compare, pre-order | AppleGadgets, Gadget&Gear | Already exists — keep, make locale-aware |

---

## 4. Phase A — i18n + Bangla foundation (unlocks everything)

**Decision (user):** English = root URL default; Bangla optional via `/bn/`; each tenant enables its own language set + preferred locale.

1. Add translation files `lang/en.json`, `lang/bn.json`; sweep all hardcoded storefront strings → `__()`/`trans()`.
2. **Locale middleware** `SetLocale`: resolve tenant enabled-locales → URL prefix (`/bn/`) → browser → persisted user pref → tenant preferred locale; `App::setLocale()`.
3. `Tenant` gains `locales` (array) + `preferred_locale` (`char(5)`). Signup/admin UI to configure.
4. Serve stored translations: replace hardcoded `translation('en')` with `translation($locale)` in `Product.php:60`, `ProductCardData.php:97,202`, `ProductController.php:105,218`, `show.blade.php:3-91`; make sort joins + Scout search locale-aware (`ProductListingService.php:128`, `Product::toSearchableArray` `Product.php:155-165`).
5. **Bangla font** self-hosted with correct unicode-range (Hind Siliguri / Noto Sans Bengali) added to Vite build; **keep Western numerals** (`৳1,250` not `৳১২৫০`) — matches Chaldal/Daraz.
6. **Centralize money:** one `money()` helper (locale-aware `Number::format`, currency symbol via `app/helpers.php:25-36`); remove the ~30 hardcoded `৳` sites.

**Files:** `lang/*`, `app/Http/Middleware/SetLocale.php`, `bootstrap/app.php`, `routes/tenant.php` (prefix), `app/Models/Tenant.php`, `app/Models/Product.php`, `app/Services/Storefront/*`, `resources/views/storefront/**`, `resources/css/app.css`, `vite.config.js`, `app/helpers.php`, migrations.

---

## 5. Phase B — Vertical abstraction + design system

**Decision (user):** owner selects an industry at signup → gets useful starter structure (categories, attributes, templates, homepage) → fully customizable later.

1. **`Tenant.industry`** enum (`electronics/mobile/fashion/grocery/sports/general`) + `config/industries.php` presets each seeding: category-tree template, attribute presets (grocery: unit/weight/pack-size; fashion: size/color), homepage-section template, nav style, theme preset.
2. **Design tokens:** add `--color-*` scale to `@theme` (`resources/css/app.css:10-18`) so palettes become data; wire dead `secondary_color` + `font_family` (`ThemeSettings.php:47-48`) to real CSS vars.
3. **Per-vertical theme presets** (grocery=green/friendly, electronics=blue/spec-led, fashion=minimal/large-imagery); owner overrides anytime.
4. **De-brand structural surfaces:** footer/header to neutral surfaces with brand accents (`footer.blade.php:5`, `desktop-header.blade.php:138`); add layout/header style options.
5. **Fix homepage reorder:** `orderBy('sort_order')` in `HomeController.php:14-16`; verify renderer + admin reorder.

**Files:** `config/industries.php`, `app/Enums/Industry.php`, `Tenant` migration, `app/Filament/Store/Pages/ThemeSettings.php`, `app/Models/StoreThemeSetting.php`, `resources/css/app.css`, header/footer components, `HomeController.php`, seeder for presets, Filament tenant create/edit forms.

---

## 6. Phase C — Grocery/fashion data model (additive, non-breaking)

1. **`unit_of_measures` entity** (kg, g, l, ml, pcs, dozen, pack) + per-product `sell_by_unit` + **per-unit pricing** + unit conversion service.
2. **Measured/loose stock:** `StockItem` gains decimal quantity + uom for loose goods; keep integer fast-path for packaged/electronics (`app/Models/StockItem.php:15,40-43`, `app/Services/InventoryService.php`).
3. **De-phone the schema:** migrate native `color/storage_gb/ram_gb/sim_type/region` variant columns to EAV-backed attributes via a data migration (no data loss); `ProductController.php:119-131` reads become attribute-driven.
4. **Bulk variant generator** (cartesian) — fashion store creates 36 SKUs in one click (`app/Filament/Store/Resources/ProductResource/RelationManagers/VariantsRelationManager.php:109-110`).

**Files:** new `UnitOfMeasure` model + migration, `StockItem`/`StockMovement` migrations, `Product`/`ProductVariant` migrations, `InventoryService`, `VariantSignatureService`, `VariantsRelationManager`, data-migration for phone columns, tests.

---

## 7. Phase D — Courier architecture (designed now, built later)

**Decision (user):** Pathao + Steadfast as first drivers; interface stays provider-agnostic.

1. **`CourierDriver` interface** + `config/couriers.php` registry — exact copy of `config/payment_gateways.php:5-9`. Drivers: `PathaoDriver`, `SteadfastDriver`; more = one config line.
2. **BD address hierarchy** (division → district → thana → area) as tables; guest checkout captures `area` (`CheckoutPage.php:28` currently omits it); shipping-zone linkage.
3. **`ShippingService` quote engine:** weight + zone + order-value + COD-amount → dynamic fee at checkout (replaces `CheckoutPage.php:88` flat copy); free-shipping threshold; pickup points tied to `Location` (`app/Models/Location.php:17`).
4. **Order persistence:** snapshot `weight_grams` + `delivery_zone` + `cod_amount` on `orders`/`order_items`; automatic COD `OrderPayment` row at placement (`PaymentMethodType::Cod`, `app/Enums/PaymentMethodType.php:9`).
5. **Courier lifecycle:** `BookShipment` job, webhook/status endpoint mapping courier status → `OrderFulfillmentStatus`/`OrderEvent`; the tracking timeline slot already exists (`resources/views/storefront/track-order/result.blade.php:23-43`).

**Files:** `app/Services/Shipping/CourierDriver.php`, `app/Services/Shipping/PathaoDriver.php`, `app/Services/Shipping/SteadfastDriver.php`, `app/Services/Shipping/ShippingService.php`, `config/couriers.php`, BD location tables/migrations + seeder, `CheckoutPage.php`, `OrderService.php`, order migrations, webhook route/controller, `app/Jobs/BookShipment.php`, tests.

---

## 8. Phase E — Vertical-native storefront patterns (config-driven)

All delivered via industry preset config + Blade components — **no per-vertical codebases**.
1. **Grocery:** category-tile-dominant home, "quick-add +" on product cards, city/area selector bar, delivery-fee-by-area display, unit/pack-size facets.
2. **Fashion:** large-imagery cards, size/color facet-first, minimal chrome, generous whitespace.
3. **Electronics:** spec-table-led PDP + facet rail (mostly exists today — keep, make locale-aware).
4. Each preset maps to the Blade component tree by config key; fallback to `general`.

---

## 9. Sequencing & out-of-scope

**Order:** Phase A (i18n/Bangla) and Phase B (vertical/theming) can run in parallel → Phase C (grocery model) → Phase D (courier) → Phase E (per-vertical UI). Phase D's BD address hierarchy can start early (couriers need it regardless).

**Explicitly NOT in this scope (flagged, don't build now):**
- RTL (Bangla is LTR; only if Arabic is ever added — would need logical-property rewrite).
- Mobile apps / PWA / public REST API.
- Multi-currency engine (BDT single-currency is acceptable; `OrderService.php:185` hardcodes `currency_rate 1.0`).

---

## 10. File-by-file impact map (summary)

| Phase | Key files |
|---|---|
| A | `lang/*`, `SetLocale` middleware, `Tenant` (+`locales`,`preferred_locale`), `Product::translation($locale)`, Scout, `money()` helper, Bangla font, all storefront views |
| B | `config/industries.php`, `Industry` enum, theme presets, `ThemeSettings` (wire `secondary_color`/`font_family`), design tokens, header/footer, `HomeController` reorder fix |
| C | `UnitOfMeasure`, per-unit pricing, measured stock, phone-column data migration, bulk variant generator |
| D | `CourierDriver`/`PathaoDriver`/`SteadfastDriver`, `ShippingService`, `config/couriers.php`, BD address hierarchy, order weight/zone/COD snapshots, webhook |
| E | Industry-preset Blade component mappings (grocery/fashion/electronics) |

Each phase ships with `tests/Feature/**` coverage and keeps `composer test` green.