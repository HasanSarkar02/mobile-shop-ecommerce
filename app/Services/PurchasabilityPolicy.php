<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The single purchasability decision, expressed exactly once as a pure
 * function of pre-fetched facts. InventoryService::isPurchasable() fetches
 * those facts for one-off checks; batched surfaces (product cards) feed the
 * same rule from resolvePurchaseStates()/availableSerialCounts() output so
 * no listing surface ever re-implements — or drifts from — this logic.
 *
 * Facts arrive as primitives so the rule itself stays free of model/enum
 * typing concerns; extraction happens once at each call site.
 */
final class PurchasabilityPolicy
{
    /**
     * @param  bool  $discontinued  variant availability is Discontinued
     * @param  bool  $nonStock  fulfillment strategy is anything other than Stock (pre-order/dropship)
     * @param  bool  $serialized  inventory type is Serialized
     * @param  bool  $backorderAllowed  stock strategy with backorders permitted
     * @param  int|string  $availableQuantity  tracked-stock quantity (0 when unknown/missing) — decimal string for measured goods
     */
    public function evaluate(
        bool $discontinued,
        bool $nonStock,
        bool $serialized,
        bool $backorderAllowed,
        int|string $availableQuantity,
        int $serialsAvailable,
        int|string $quantity = 1,
    ): bool {
        if ($discontinued) {
            return false;
        }

        if ($nonStock) {
            return true;
        }

        if ($serialized) {
            return $serialsAvailable >= (int) $quantity;
        }

        if ($backorderAllowed) {
            return true;
        }

        // Decimal-safe comparison at scale 3 (Phase C-3): bccomp handles both
        // whole-unit strings ("5.000") and integer callers ("5").
        return bccomp((string) $availableQuantity, (string) $quantity, 3) !== -1;
    }
}
