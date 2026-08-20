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

        $states = $this->resolveStates($products);
        $wishlistedIds = $wishlistedProductIds ?? collect();

        return $products->values()
            ->map(fn (Product $product): array => $this->build($product, $states, $wishlistedIds));
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
     * @param  Collection<int, int>  $wishlistedIds
     * @return array<string, mixed>
     */
    private function build(Product $product, Collection $states, Collection $wishlistedIds): array
    {
        $translation = $product->translation('en');
        $variant = $this->usableVariant($product, $states);

        $image = $product->getFirstMediaUrl('images', 'thumb');
        $firstMedia = $product->media->first();

        $discount = null;
        if ($variant !== null && $variant->compare_at_price && $variant->compare_at_price > $variant->price) {
            $discount = (int) round((($variant->compare_at_price - $variant->price) / $variant->compare_at_price) * 100);
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
            'is_official_import' => (bool) $product->is_official_import,
            'discount_percentage' => $discount,
            'emi_available' => $this->emiAvailable($product),
            'reviews_count' => (int) ($product->reviews_count ?? 0),
            'average_rating' => $product->average_rating !== null ? (string) $product->average_rating : null,
            'wishlisted' => $wishlistedIds->contains($product->id),
            'requires_selection' => $product->variants->where('is_active', true)->count() > 1,
            'variant' => $variant !== null ? $this->variantView($variant, $states) : null,
            'cta' => $this->ctaView($product, $states),
        ];
    }

    /**
     * The cheapest ACTIVE variant that can currently be purchased; when no
     * active variant is purchasable, falls back to the cheapest active one so
     * the card still shows a price and the correct out-of-stock state.
     */
    private function usableVariant(Product $product, Collection $states): ?ProductVariant
    {
        $active = $product->variants
            ->where('is_active', true)
            ->sortBy(fn (ProductVariant $variant): int => (int) $variant->price)
            ->values();

        if ($active->isEmpty()) {
            return null;
        }

        foreach ($active as $variant) {
            if ($this->isPurchasable($variant, $states)) {
                return $variant;
            }
        }

        return $active->first();
    }

    private function isPurchasable(ProductVariant $variant, Collection $states): bool
    {
        if ($variant->availability === VariantAvailability::Discontinued) {
            return false;
        }

        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return true;
        }

        if ($variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny) {
            return true;
        }

        return ($states->get($variant->id)['available_quantity'] ?? 0) >= 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function variantView(ProductVariant $variant, Collection $states): array
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
            'purchasable' => $this->isPurchasable($variant, $states),
        ];
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
    private function ctaView(Product $product, Collection $states): array
    {
        $active = $product->variants->where('is_active', true)->values();
        $url = $this->urls->canonicalRoute(tenant(), 'storefront.product', [$product->translation('en')?->slug ?? $product->id]);

        if ($active->count() > 1) {
            return [
                'type' => 'select_options',
                'label' => 'Select Options',
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

        if (! $this->isPurchasable($variant, $states)) {
            return [
                'type' => 'disabled',
                'label' => 'Out of Stock',
                'variant_id' => null,
                'url' => $url,
                'disabled' => true,
            ];
        }

        $stockStatus = $states->get($variant->id)['stock_status'] ?? StockStatus::OutOfStock;

        $label = match (true) {
            $variant->fulfillment_strategy === FulfillmentStrategy::Preorder => 'Pre-Order',
            $stockStatus === StockStatus::OutOfStock && $variant->backorder_policy === BackorderPolicy::Notify => 'Backorder',
            default => 'Add to Cart',
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
