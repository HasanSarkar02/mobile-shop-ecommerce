<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Support\IndustryConfig;

final class CartPricingService
{
    private const INTERMEDIATE_SCALE = 6;

    private const FINAL_SCALE = 3;

    public function isPerUnitEnabled(?Product $product = null): bool
    {
        if ($product !== null) {
            $uom = $product->relationLoaded('uom') ? $product->getRelation('uom') : $product->uom;
            if ($uom instanceof UnitOfMeasure && $uom->isDiscrete()) {
                return false;
            }
        }

        return (bool) IndustryConfig::currentGet('pricing.per_unit_enabled', false);
    }

    /**
     * Base quantity that variant_base_price covers. Returns decimal string scale 3.
     */
    public function getBaseQuantity(?Product $product): string
    {
        if ($product === null) {
            return '1.000';
        }

        $raw = $product->base_uom_quantity ?? $product->sell_by_unit ?? '1.000';

        $formatted = number_format((float) $raw, 3, '.', '');

        return bccomp($formatted, '0', 3) === 1 ? $formatted : '1.000';
    }

    /**
     * Industry-aware line total in minor units (cents). Uses bcmath for BDT precision.
     *
     * Grocery (per-unit): (price / base_qty) * qty
     * Standard (fashion/electronics): price * qty
     */
    public function calculateLineTotal(ProductVariant $variant, int|float|string $quantity): int
    {
        $qty = $this->normalizeQty($quantity);
        $variant->loadMissing('product.uom');

        $product = $variant->product;

        if (! $this->isPerUnitEnabled($product)) {
            $raw = bcmul((string) $variant->price, $qty, self::FINAL_SCALE);

            return (int) bcadd($raw, '0.5', 0);
        }

        $baseQty = $this->getBaseQuantity($product);

        // Guard division by zero; fallback to standard pricing
        if (bccomp($baseQty, '0', self::FINAL_SCALE) !== 1) {
            $raw = bcmul((string) $variant->price, $qty, self::FINAL_SCALE);

            return (int) bcadd($raw, '0.5', 0);
        }

        $perBase = bcdiv((string) $variant->price, $baseQty, self::INTERMEDIATE_SCALE);
        $raw = bcmul($perBase, $qty, self::FINAL_SCALE);

        return (int) bcadd($raw, '0.5', 0);
    }

    /**
     * Resolve unit price to store in cart_items.unit_price for per-unit industries.
     * Stored as integer cents per base unit (rounded). For standard industries returns raw variant price.
     */
    public function resolveUnitPrice(ProductVariant $variant): int
    {
        $variant->loadMissing('product.uom');

        $product = $variant->product;

        if (! $this->isPerUnitEnabled($product)) {
            return (int) $variant->price;
        }

        $baseQty = $this->getBaseQuantity($product);

        if (bccomp($baseQty, '1.000', self::FINAL_SCALE) === 0) {
            return (int) $variant->price;
        }

        if (bccomp($baseQty, '0', self::FINAL_SCALE) !== 1) {
            return (int) $variant->price;
        }

        $perBase = bcdiv((string) $variant->price, $baseQty, self::INTERMEDIATE_SCALE);

        return (int) bcadd($perBase, '0.5', 0);
    }

    private function normalizeQty(int|float|string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new \InvalidArgumentException('Quantity must be numeric.');
        }

        return number_format((float) $quantity, 3, '.', '');
    }
}
