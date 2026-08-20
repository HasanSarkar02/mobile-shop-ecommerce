<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\BackorderPolicy;
use App\Enums\FulfillmentStrategy;
use App\Enums\StaticPageStatus;
use App\Enums\StockStatus;
use App\Http\Controllers\Controller;
use App\Jobs\IncrementProductViewCount;
use App\Models\EmiPlan;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ShippingMethod;
use App\Models\StaticPage;
use App\Services\CompareService;
use App\Services\InventoryService;
use App\Services\RecentlyViewedService;
use App\Services\Storefront\ProductCardData;
use App\Services\WishlistService;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    /**
     * Known policy-page slugs (the storefront.page route renders by slug). A
     * link is rendered only when a published page with this slug exists — see
     * resolvePolicyLinks(). This is a display-side convention; it never
     * creates or invents pages.
     */
    private const POLICY_SLUGS = [
        'Delivery' => ['delivery-policy'],
        'Warranty' => ['warranty-policy'],
        'Payment / EMI' => ['payment-policy', 'emi-payment-policy'],
        'Return / Exchange' => ['return-policy', 'exchange-policy'],
        'Authenticity' => ['authenticity-policy'],
    ];

    /**
     * Resolves the policy links shown near the buy box. One tenant-scoped
     * query for the whole set, so there is no per-link N+1. Categories that
     * have no matching published page are silently skipped.
     *
     * @return array<int, array{label: string, slug: string}>
     */
    private function resolvePolicyLinks(): array
    {
        $slugs = array_values(array_unique(array_merge(...array_values(self::POLICY_SLUGS))));

        $pages = StaticPage::query()
            ->whereIn('slug', $slugs)
            ->where('status', StaticPageStatus::Published)
            ->get()
            ->keyBy('slug');

        $links = [];

        foreach (self::POLICY_SLUGS as $label => $candidates) {
            foreach ($candidates as $slug) {
                if ($pages->has($slug)) {
                    $links[] = ['label' => $label, 'slug' => $slug];

                    break;
                }
            }
        }

        return $links;
    }

    public function show(string $slug, RecentlyViewedService $recentlyViewed, WishlistService $wishlists, CompareService $compare, InventoryService $inventory, ProductCardData $cards, TenantUrlGenerator $urls)
    {
        $product = Product::published()
            ->whereHas('translations', fn ($query) => $query->where('slug', $slug))
            ->with([
                'translations',
                'brand',
                'category',
                'variants.media',
                'variants.attributeValues.attributeDefinition',
                'variants.attributeValues.attributeOption',
                'media',
                'attributeValues.attributeDefinition',
                'attributeValues.attributeOption',
                'emiPlans' => fn ($query) => $query->where('active', true),
                'faqs' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'approvedReviews.customer',
            ])
            ->firstOrFail();

        IncrementProductViewCount::dispatch($product->id, $product->tenant_id);
        $recentlyViewed->record($product, auth('customer')->user());

        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), request()->cookie('wishlist_token'));
        $isWishlisted = $wishlist->items()->where('product_id', $product->id)->exists();
        $isComparing = in_array($product->id, $compare->ids(), true);

        $dimensions = [];
        $purchaseStates = $inventory->resolvePurchaseStates($product->variants);
        $imageAltFallback = $product->translation('en')?->name ?: '';

        // A product needs explicit option selection only when more than one
        // ACTIVE variant exists. A single active variant is auto-resolved on
        // load so its buy box is immediately usable. Inactive variants never
        // participate in selection (they are not purchasable surfaces).
        $activeVariants = $product->variants->where('is_active', true)->values();
        $requiresSelection = $activeVariants->count() > 1;
        $initialVariantId = $activeVariants->count() === 1 ? $activeVariants->first()->id : null;

        $variantsData = $product->variants->map(function ($variant) use (&$dimensions, $purchaseStates, $imageAltFallback) {
            $dims = [];
            $meta = [];

            // Native phone/electronics columns: source of truth when populated.
            if ($variant->color !== null) {
                $dims['color'] = (string) $variant->color;
                $meta['color'] ??= ['code' => 'color', 'label' => 'Color', 'suffix' => ''];
            }
            if ($variant->storage_gb !== null) {
                $dims['storage'] = (string) $variant->storage_gb;
                $meta['storage'] ??= ['code' => 'storage', 'label' => 'Storage', 'suffix' => 'GB'];
            }
            if ($variant->region !== null) {
                $dims['region'] = (string) $variant->region;
                $meta['region'] ??= ['code' => 'region', 'label' => 'Region', 'suffix' => ''];
            }

            // Generic variant-defining attributes (Size, Weight, Shade, ...).
            foreach ($variant->attributeValues as $value) {
                if ($value->product_variant_id === null
                    || $value->attributeDefinition === null
                    || ! $value->attributeDefinition->is_variant_defining) {
                    continue;
                }

                $code = $value->attributeDefinition->code;
                $dims[$code] ??= $value->displayValue() ?? (string) ($value->attributeOption?->value ?? '');
                $meta[$code] ??= [
                    'code' => $code,
                    'label' => $value->attributeDefinition->label,
                    'suffix' => (string) ($value->attributeDefinition->unit ?? ''),
                ];
            }

            foreach ($meta as $code => $definition) {
                $dimensions[$code] ??= $definition;
            }

            $state = $purchaseStates->get($variant->id) ?? [
                'stock_status' => StockStatus::OutOfStock,
                'available_quantity' => 0,
                'low_stock_threshold' => null,
            ];

            // Mirrors InventoryService::isPurchasable() so the PDP never lets a
            // shopper attempt an action the server would reject.
            $purchasable = match (true) {
                $variant->availability->value === 'discontinued' => false,
                $variant->fulfillment_strategy !== FulfillmentStrategy::Stock => true,
                $variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny => true,
                default => $state['available_quantity'] >= 1,
            };

            return [
                'id' => $variant->id,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'availability' => $variant->availability->value,
                'fulfillment_strategy' => $variant->fulfillment_strategy->value,
                'purchase_state' => $state['stock_status']->value,
                'available_quantity' => $state['available_quantity'],
                'backorder_policy' => $variant->backorder_policy?->value,
                'expected_available_at' => $variant->expected_available_at?->format('M j, Y'),
                'purchasable' => $purchasable,
                'is_active' => (bool) $variant->is_active,
                'images' => $variant->getMedia('images')->map(fn ($media) => [
                    'src' => $media->getUrl('large'),
                    'alt' => media_alt($media, $imageAltFallback),
                ])->all(),
                'dims' => $dims,
            ];
        })->values();

        $dimensions = array_values($dimensions);

        $productImages = $product->getMedia('images')
            ->map(fn ($media) => [
                'src' => $media->getUrl('large'),
                'alt' => media_alt($media, $imageAltFallback),
            ])
            ->values()
            ->all();

        // Product-level specifications grouped by attribute group, ordered by
        // group_sort_order (groups) then sort_order (attributes within a group).
        // Definitions without a group fall back to the generic "General" group,
        // so legacy products keep rendering without any data migration.
        $specificationGroups = $product->attributeValues
            ->whereNull('product_variant_id')
            ->filter(fn (ProductAttributeValue $value) => $value->attributeDefinition !== null)
            ->groupBy(fn (ProductAttributeValue $value) => $value->attributeDefinition->group ?: 'General')
            ->map(function ($items, string $group): array {
                return [
                    'group' => $group,
                    'group_sort_order' => $items->min(fn (ProductAttributeValue $value) => $value->attributeDefinition->group_sort_order ?? 0),
                    'items' => $items
                        ->sortBy(fn (ProductAttributeValue $value) => $value->attributeDefinition->sort_order ?? 0)
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group): array => [$group['group_sort_order'], $group['group']])
            ->values();
        $translation = $product->translation('en');
        $canonicalProductUrl = $translation?->slug
            ? $urls->canonicalRoute(tenant(), 'storefront.product', [$translation->slug])
            : null;

        $productJsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'url' => $canonicalProductUrl,
            'name' => $translation?->name,
            'image' => array_map(fn ($image) => $image['src'], $productImages),
            'description' => $translation?->description ? strip_tags($translation->description) : null,
            'sku' => $product->variants->first()?->sku,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => $variantsData->map(fn ($v) => [
                '@type' => 'Offer',
                'price' => number_format($v['price'] / 100, 2, '.', ''),
                'priceCurrency' => tenant()->currency,
                'availability' => $v['availability'] === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ])->all(),
            'aggregateRating' => $product->reviews_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $product->average_rating,
                'reviewCount' => (string) $product->reviews_count,
            ] : null,
        ]);

        $faqJsonLd = $product->faqs->isNotEmpty() ? [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'url' => $canonicalProductUrl,
            'mainEntity' => $product->faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq->answer)],
            ])->all(),
        ] : null;

        $relatedProducts = $product->relatedProducts()
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->published()
            ->limit(4)
            ->get();

        // Merchandising rails. Each relation is tenant-scoped through the
        // Product BelongsToTenant global scope, ordered by the admin-defined
        // pivot sort_order, and capped at 4 items.
        $crossSells = $this->merchandiseRail($product->crossSells());
        $upsells = $this->merchandiseRail($product->upsells());
        $frequentlyBought = $this->merchandiseRail($product->frequentlyBoughtWith());
        $compatibleAccessories = $this->merchandiseRail($product->compatibleAccessories());

        $policyLinks = $this->resolvePolicyLinks();

        // EMI plans (active-only, loaded above) serialized for the client-side
        // recompute. Only rates + tenures are sent; no bank-specific logic.
        $emiData = $product->emiPlans
            ->map(fn (EmiPlan $plan) => [
                'bank_name' => $plan->bank_name,
                'tenure' => $plan->tenure_months,
                'rate' => (float) $plan->interest_rate,
            ])
            ->values()
            ->all();

        // Variant-agnostic trust strip: active shipping + payment methods the
        // store actually accepts. Never invents settings — rendered only from
        // tenant-scoped rows that exist.
        $shippingMethods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Recently-viewed rail: reuses the existing tracking pipeline, keeps
        // its most-recent-first ordering, and never queries when empty.
        $recentlyViewedProducts = collect();
        $recentIds = $recentlyViewed->recentProductIds(auth('customer')->user());
        $recentIds = array_values(array_filter($recentIds, fn (int $id) => $id !== $product->id));

        if ($recentIds !== []) {
            $recentlyViewedProducts = Product::published()
                ->whereIn('id', $recentIds)
                ->with(['translations', 'variants', 'media', 'emiPlans'])
                ->get()
                ->sortBy(fn (Product $recent) => array_search($recent->id, $recentIds, true))
                ->values()
                ->take(8);
        }

        // All rail products share one wishlist-membership lookup so card state
        // is correct on first paint for every rail (homepage/PDP/recently-viewed).
        $railProducts = collect([$relatedProducts, $crossSells, $upsells, $frequentlyBought, $compatibleAccessories, $recentlyViewedProducts])
            ->flatMap(fn (Collection $rail) => $rail);
        $wishlistedProductIds = $wishlists->wishlistedProductIds($railProducts->pluck('id'));

        $relatedCards = $cards->forMany($relatedProducts, $wishlistedProductIds);
        $crossSellCards = $cards->forMany($crossSells, $wishlistedProductIds);
        $upsellCards = $cards->forMany($upsells, $wishlistedProductIds);
        $frequentlyBoughtCards = $cards->forMany($frequentlyBought, $wishlistedProductIds);
        $compatibleAccessoryCards = $cards->forMany($compatibleAccessories, $wishlistedProductIds);
        $recentlyViewedCards = $cards->forMany($recentlyViewedProducts, $wishlistedProductIds);

        return view('storefront.products.show', compact(
            'product', 'variantsData', 'dimensions', 'productImages', 'specificationGroups', 'productJsonLd', 'faqJsonLd', 'isWishlisted', 'isComparing',
            'relatedCards', 'crossSellCards', 'upsellCards', 'frequentlyBoughtCards', 'compatibleAccessoryCards', 'recentlyViewedCards', 'policyLinks', 'emiData', 'shippingMethods', 'paymentMethods',
            'requiresSelection', 'initialVariantId',
        ));
    }

    /**
     * Loads one product-relation merchandising rail for the storefront PDP.
     * Only published products are shown, the current product is excluded, and
     * the BelongsToTenant global scope keeps the rail tenant-isolated. Ordering
     * follows the admin-defined pivot sort_order; the rail is capped at 4.
     */
    private function merchandiseRail(BelongsToMany $relation): Collection
    {
        $currentProductId = $relation->getParent()->id;

        return $relation
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->where('products.id', '!=', $currentProductId)
            ->published()
            ->orderByPivot('sort_order')
            ->limit(4)
            ->get();
    }
}
