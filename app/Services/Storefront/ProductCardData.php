<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\BackorderPolicy;
use App\Enums\FulfillmentStrategy;
use App\Enums\StockStatus;
use App\Enums\VariantAvailability;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\PurchasabilityPolicy;
use App\Support\IndustryConfig;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Single source of truth for product-card presentation data. Every storefront
 * surface (Livewire catalog, homepage rails, PDP rails, recently-viewed,
 * wishlist) resolves its card view-models through here, so business rules
 * (cheapest active variant, discount, availability) live once instead of
 * being duplicated in Blade templates.
 *
 * Consumers must eager-load `translations`, `variants`, `media`, and
 * `emiPlans` on the products they pass in; this service only reads what is
 * already loaded and never issues per-card queries. Stock states are resolved
 * in one batched call per product collection via InventoryService.
 */
class ProductCardData
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly TenantUrlGenerator $urls,
    ) {}

    /**
     * Build card view-models for a product collection or paginator.
     *
     * @param  Collection<int, Product>|LengthAwarePaginator<Product>  $products
     * @param  Collection<int, int>|null  $wishlistedProductIds
     * @return Collection<int, array<string, mixed>>
     */
    public function forMany(Collection|LengthAwarePaginator $products, ?Collection $wishlistedProductIds = null): Collection
    {
        if ($products instanceof LengthAwarePaginator) {
            $products = $products->getCollection();
        }

        if ($products->isEmpty()) {
            return collect();
        }

        // Ensure UOM is available for grocery cards without N+1 (Phase C).
        if (! $products->first()->relationLoaded('uom')) {
            foreach ($products as $product) {
                $product->loadMissing('uom');
            }
        }

        $states = $this->resolveStates($products);
        $facts = $this->inventory->purchasabilityFacts($products->flatMap->variants->values());
        $wishlistedIds = $wishlistedProductIds ?? collect();

        return $products->values()
            ->map(fn (Product $product): array => $this->build($product, $states, $facts, $wishlistedIds));
    }

    /**
     * Build a single card view-model.
     *
     * @param  Collection<int, int>|null  $wishlistedProductIds
     * @return array<string, mixed>
     */
    public function forProduct(Product $product, ?Collection $wishlistedProductIds = null): array
    {
        return $this->forMany(collect([$product]), $wishlistedProductIds)->first();
    }

    /**
     * Batch stock states across every variant of the collection so a card
     * grid never resolves purchase states per product.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, array{stock_status: StockStatus, available_quantity: int, low_stock_threshold: ?int}>
     */
    private function resolveStates(Collection $products): Collection
    {
        $variants = $products->flatMap->variants->values();

        if ($variants->isEmpty()) {
            return collect();
        }

        return $this->inventory->resolvePurchaseStates($variants);
    }

    /**
     * @param  Collection<int, array{stock_status: StockStatus, available_quantity: int, low_stock_threshold: ?int}>  $states
     * @param  Collection<int, array{discontinued: bool, non_stock: bool, serialized: bool, backorder_allowed: bool, serials_available: int}>  $facts
     * @param  Collection<int, int>  $wishlistedIds
     * @return array<string, mixed>
     */
    private function build(Product $product, Collection $states, Collection $facts, Collection $wishlistedIds): array
    {
        $translation = $product->translation() ?? $product->translation('en');
        $variant = $this->usableVariant($product, $states, $facts);

        $image = $product->getFirstMediaUrl('images', 'thumb');
        $firstMedia = $product->media->first();

        $discount = null;
        if ($variant !== null && $variant->compare_at_price && $variant->compare_at_price > $variant->price) {
            $discount = (int) round((($variant->compare_at_price - $variant->price) / $variant->compare_at_price) * 100);
        }

        // Gallery for hover-preview (F.6): additive, never required. Limit to
        // 5 images to keep the card payload small. When `media` is already
        // eager-loaded (the mandate for forMany callers) we filter the loaded
        // relation to avoid an N+1; otherwise fall back to getMedia().
        $mediaCollection = $product->relationLoaded('media')
            ? $product->media->where('collection_name', 'images')->take(5)
            : $product->getMedia('images')->take(5);

        $fallbackName = $translation !== null ? $translation->name : '';
        $galleryImages = collect($mediaCollection)->map(fn ($media) => [
            'src' => $media->getUrl('thumb'),
            'alt' => media_alt($media, $fallbackName),
        ])->values()->all();

        // Per-industry hover toggle (F.5 config). "never auto-enable" means
        // this stays false for every preset until a storefront explicitly opts
        // in; general is the safe fallback when the tenant has no industry yet.
        // Uses currentGet so TenantIndustry enum (B-1 cast) is resolved via
        // IndustryConfig::normalize without a strict string type collision.
        $hoverGalleryEnabled = (bool) IndustryConfig::currentGet('card.hover_gallery_enabled', false);
        $requiresSelection = $product->variants->where('is_active', true)->count() > 1;

        $modalVariants = [];
        $modalDimensions = [];
        if ($requiresSelection) {
            $modalPayload = $this->variantModalPayload($product, $states, $facts);
            $modalVariants = $modalPayload['variants'];
            $modalDimensions = $modalPayload['dimensions'];
        }

        // Free delivery flag only when truly free (shipping method free). For grocery, show when on sale (has discount) as proxy for offer free delivery.
        $hasFreeDelivery = false;
        if ($discount !== null) {
            $hasFreeDelivery = true;
        }

        return [
            'id' => $product->id,
            'url' => $this->urls->canonicalRoute(tenant(), 'storefront.product', [$translation?->slug ?? $product->id]),
            'name' => $translation?->name,
            'image' => $image ?: null,
            'image_alt' => $firstMedia !== null
                ? media_alt($firstMedia, $translation?->name ?? '')
                : ($translation?->name ?? ''),
            'has_image' => (bool) $image,
            'gallery_images' => $galleryImages,
            'hover_gallery_enabled' => $hoverGalleryEnabled && count($galleryImages) > 1,
            'is_official_import' => (bool) $product->is_official_import,
            'discount_percentage' => $discount,
            'emi_available' => $this->emiAvailable($product),
            'has_free_delivery' => $hasFreeDelivery,
            'reviews_count' => (int) ($product->reviews_count ?? 0),
            'average_rating' => $product->average_rating !== null ? (string) $product->average_rating : null,
            'wishlisted' => $wishlistedIds->contains($product->id),
            'requires_selection' => $requiresSelection,
            'variant' => $variant !== null ? $this->variantView($variant, $states, $facts) : null,
            'cta' => $this->ctaView($product, $states, $facts),
            'modal_variants' => $modalVariants,
            'modal_dimensions' => $modalDimensions,
            'product' => $product,
        ];
    }

    /**
     * The cheapest ACTIVE variant that can currently be purchased; when no
     * active variant is purchasable, falls back to the cheapest active one so
     * the card still shows a price and the correct out-of-stock state.
     */
    private function usableVariant(Product $product, Collection $states, Collection $facts): ?ProductVariant
    {
        $active = $product->variants
            ->where('is_active', true)
            ->sortBy(fn (ProductVariant $variant): int => (int) $variant->price)
            ->values();

        if ($active->isEmpty()) {
            return null;
        }

        foreach ($active as $variant) {
            if ($this->isPurchasable($variant, $states, $facts)) {
                return $variant;
            }
        }

        return $active->first();
    }

    /**
     * Canonical rule via PurchasabilityPolicy — the same decision
     * InventoryService::isPurchasable() makes, fed from batched facts.
     */
    private function isPurchasable(ProductVariant $variant, Collection $states, Collection $facts): bool
    {
        $fact = $facts->get($variant->id) ?? [
            'discontinued' => false,
            'non_stock' => false,
            'serialized' => false,
            'backorder_allowed' => false,
            'serials_available' => 0,
        ];

        $state = $states->get($variant->id);
        // Prefer decimal string (measured goods 0.750) over truncated int.
        // Falls back to int for backward compatibility with older state shape.
        $available = $state['available_quantity_decimal'] ?? (string) ($state['available_quantity'] ?? '0');

        return (new PurchasabilityPolicy)->evaluate(
            discontinued: $fact['discontinued'],
            nonStock: $fact['non_stock'],
            serialized: $fact['serialized'],
            backorderAllowed: $fact['backorder_allowed'],
            availableQuantity: $available,
            serialsAvailable: $fact['serials_available'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function variantView(ProductVariant $variant, Collection $states, Collection $facts): array
    {
        $stockStatus = $states->get($variant->id)['stock_status'] ?? StockStatus::OutOfStock;

        return [
            'id' => $variant->id,
            'price' => (int) $variant->price,
            'compare_at_price' => $variant->compare_at_price !== null ? (int) $variant->compare_at_price : null,
            'availability' => $variant->availability->value,
            'stock_status' => $stockStatus->value,
            'is_preorder' => $variant->fulfillment_strategy === FulfillmentStrategy::Preorder,
            'is_out_of_stock' => $stockStatus === StockStatus::OutOfStock || $variant->availability === VariantAvailability::Discontinued,
            'purchasable' => $this->isPurchasable($variant, $states, $facts),
        ];
    }

    /**
     * Build the per-card variant-selection payload for the F.7 modal. Uses the
     * same dims/meta logic as ProductController::show so the modal can reuse
     * the shared `x-storefront.variant-selector` and `variantSelectionState`
     * engine without a second implementation. Tenant isolation is preserved
     * because variants are already tenant-scoped via the product.
     *
     * @return array{variants: array<int, array<string, mixed>>, dimensions: array<int, array<string, string>>}
     */
    private function variantModalPayload(Product $product, Collection $states, Collection $facts): array
    {
        // Ensure variant attribute values are available for dimension building
        // without issuing per-variant queries when the relation is already eager-loaded.
        $product->loadMissing('variants.attributeValues.attributeDefinition', 'variants.attributeValues.attributeOption');

        /** @var array<string, array{code: string, label: string, suffix: string}> $dimensions */
        $dimensions = [];
        $variantsData = [];

        foreach ($product->variants as $variant) {
            /** @var array<string, string> $dims */
            $dims = [];
            /** @var array<string, array{code: string, label: string, suffix: string}> $meta */
            $meta = [];

            if ($variant->color !== null) {
                $dims['color'] = (string) $variant->color;
                if (! isset($meta['color'])) {
                    $meta['color'] = ['code' => 'color', 'label' => 'Color', 'suffix' => ''];
                }
            }
            if ($variant->storage_gb !== null) {
                $dims['storage'] = (string) $variant->storage_gb;
                if (! isset($meta['storage'])) {
                    $meta['storage'] = ['code' => 'storage', 'label' => 'Storage', 'suffix' => 'GB'];
                }
            }
            if ($variant->region !== null) {
                $dims['region'] = (string) $variant->region;
                if (! isset($meta['region'])) {
                    $meta['region'] = ['code' => 'region', 'label' => 'Region', 'suffix' => ''];
                }
            }

            foreach ($variant->attributeValues as $value) {
                if ($value->product_variant_id === null || $value->attributeDefinition === null || ! $value->attributeDefinition->is_variant_defining) {
                    continue;
                }
                $code = $value->attributeDefinition->code;
                $optionValue = $value->attributeOption !== null ? $value->attributeOption->value : '';
                if (! isset($dims[$code])) {
                    $dims[$code] = $value->displayValue() ?? (string) $optionValue;
                }
                if (! isset($meta[$code])) {
                    $meta[$code] = [
                        'code' => $code,
                        'label' => $value->attributeDefinition->label,
                        'suffix' => (string) ($value->attributeDefinition->unit ?? ''),
                    ];
                }
            }

            foreach ($meta as $code => $definition) {
                if (! isset($dimensions[$code])) {
                    $dimensions[$code] = $definition;
                }
            }

            $state = $states->get($variant->id) ?? ['stock_status' => StockStatus::OutOfStock, 'available_quantity' => 0];
            $purchasable = $this->isPurchasable($variant, $states, $facts);

            $variantsData[] = [
                'id' => $variant->id,
                'price' => (int) $variant->price,
                'compare_at_price' => $variant->compare_at_price !== null ? (int) $variant->compare_at_price : null,
                'availability' => $variant->availability->value,
                'fulfillment_strategy' => $variant->fulfillment_strategy->value,
                'purchase_state' => $state['stock_status']->value,
                'available_quantity' => $state['available_quantity'],
                'backorder_policy' => $variant->backorder_policy?->value,
                'purchasable' => $purchasable,
                'is_active' => (bool) $variant->is_active,
                'dims' => $dims,
            ];
        }

        return ['variants' => $variantsData, 'dimensions' => array_values($dimensions)];
    }

    /**
     * Resolve the card-level purchase CTA. All business rules live here (never
     * in Blade): a multi-active-variant product always routes to the PDP for
     * option selection; a single active variant maps to the same purchase-state
     * rules as the PDP buy box (pre-order / backorder / out-of-stock /
     * discontinued); a discontinued or variant-less product renders no CTA.
     *
     * @return array{type: string, label: ?string, variant_id: ?int, url: string, disabled: bool}
     */
    private function ctaView(Product $product, Collection $states, Collection $facts): array
    {
        $active = $product->variants->where('is_active', true)->values();
        $localeTranslation = $product->translation() ?? $product->translation('en');
        $url = $this->urls->canonicalRoute(tenant(), 'storefront.product', [$localeTranslation?->slug ?? $product->id]);

        if ($active->count() > 1) {
            return [
                'type' => 'select_options',
                'label' => __('Select Options'),
                'variant_id' => null,
                'url' => $url,
                'disabled' => false,
            ];
        }

        $variant = $active->first();

        if ($variant === null || $variant->availability === VariantAvailability::Discontinued) {
            return [
                'type' => 'none',
                'label' => null,
                'variant_id' => null,
                'url' => $url,
                'disabled' => true,
            ];
        }

        // Single-variant products should always show Add to Cart (not disabled) — stock/price validation
        // is handled when actually adding to cart (InventoryService), matching Bangladeshi grocery UX where
        // every single-pack card is tappable. Multi-variant already goes to select_options above.
        // Keep disabled only for true discontinued; otherwise fall through to add_to_cart.
        // if (! $this->isPurchasable($variant, $states, $facts)) {
        //     return disabled — removed per fix request
        // }

        $stockStatus = $states->get($variant->id)['stock_status'] ?? StockStatus::OutOfStock;

        $label = match (true) {
            $variant->fulfillment_strategy === FulfillmentStrategy::Preorder => __('Pre-Order'),
            $stockStatus === StockStatus::OutOfStock && $variant->backorder_policy === BackorderPolicy::Notify => __('Backorder'),
            default => __('Add to Cart'),
        };

        return [
            'type' => 'add_to_cart',
            'label' => $label,
            'variant_id' => $variant->id,
            'url' => $url,
            'disabled' => false,
        ];
    }

    private function emiAvailable(Product $product): bool
    {
        if ($product->relationLoaded('emiPlans')) {
            return $product->emiPlans->isNotEmpty();
        }

        return $product->emiPlans()->exists();
    }
}
