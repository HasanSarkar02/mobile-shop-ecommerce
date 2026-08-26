<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\OrderFulfillmentStatus;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Services\OrderService;

/**
 * Single source of truth for "how much cash must the courier collect for this
 * consignment", in integer minor units.
 *
 * Both courier drivers used to inline their own version of this
 * (`grand_total - SUM(paid)`), ignore the fulfillment they were handed, and hand
 * every consignment on a split order the *whole* order due — so a two-consignment
 * order tried to collect the total twice. Centralising it here fixes that and
 * removes the duplicated money rule.
 */
class CodAmountResolver
{
    public function __construct(private OrderService $orders) {}

    /**
     * Minor units of cash the courier must collect for THIS consignment.
     *
     * A null fulfillment means the caller has no consignment split to respect
     * (an order with no fulfillment rows at all), in which case the whole due
     * belongs to the single shipment being created.
     */
    public function minorUnitsFor(Order $order, ?OrderFulfillment $fulfillment): int
    {
        // A prepaid order that was never paid must not be silently converted to
        // cash-on-delivery at the customer's door: only a COD order carries cash.
        if ($order->paymentMethod?->isCod() !== true) {
            return 0;
        }

        $due = max(0, (int) $order->grand_total - $this->orders->amountPaid($order));

        if ($due === 0) {
            return 0;
        }

        if ($fulfillment === null) {
            return $due;
        }

        return $this->allocate($order, $due)[(int) $fulfillment->getKey()] ?? 0;
    }

    /**
     * Split the outstanding due across the consignments that still have cash to
     * collect, weighted by the value of the goods each one carries.
     *
     * Delivered and failed consignments are excluded rather than allocated zero:
     * cash already collected has already reduced `$due` via amountPaid(), so the
     * remaining consignments must carry the whole remainder between them.
     *
     * Uses largest-remainder so the parts sum to exactly `$due` — no paisa is
     * created or lost. Ties break on the lowest fulfillment id, which makes the
     * allocation identical on every repeated call.
     *
     * @return array<int, int> fulfillment id => minor units
     */
    public function allocate(Order $order, int $due): array
    {
        $collectible = $order->fulfillments()
            ->whereNotIn('status', [
                OrderFulfillmentStatus::Delivered->value,
                OrderFulfillmentStatus::Failed->value,
            ])
            ->orderBy('id')
            ->get();

        if ($collectible->isEmpty()) {
            return [];
        }

        if ($collectible->count() === 1) {
            return [(int) $collectible->first()->getKey() => $due];
        }

        /** @var array<int, int> $weights */
        $weights = [];

        foreach ($collectible as $fulfillment) {
            $weights[(int) $fulfillment->getKey()] = max(0, (int) $fulfillment->items()->sum('line_total'));
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            // Nothing to weigh by (items were never linked to a fulfillment):
            // split evenly rather than loading the whole due onto one consignment.
            $weights = array_map(static fn (): int => 1, $weights);
            $totalWeight = array_sum($weights);
        }

        $allocated = [];
        $remainders = [];

        foreach ($weights as $id => $weight) {
            $share = $due * $weight;
            $allocated[$id] = intdiv($share, $totalWeight);
            $remainders[$id] = $share % $totalWeight;
        }

        $ids = array_keys($weights);
        usort($ids, static fn (int $a, int $b): int => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));

        $shortfall = max(0, $due - array_sum($allocated));

        foreach (array_slice($ids, 0, $shortfall) as $id) {
            $allocated[$id]++;
        }

        return $allocated;
    }
}
