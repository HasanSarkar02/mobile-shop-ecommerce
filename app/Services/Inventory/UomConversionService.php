<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\UomType;
use App\Exceptions\IncompatibleUomException;
use App\Models\UnitOfMeasure;
use InvalidArgumentException;

/**
 * Unit-of-measure conversion engine (Phase C-1 #46).
 *
 * Converts a quantity between two UOMs of the SAME type through each unit's
 * base_conversion_factor (quantity → base units → target unit). All arithmetic
 * is bcmath on fixed-point strings — never floats — matching the InventoryService
 * decimal contract (scale 3 output; scale 6 intermediates so tiny factors do
 * not lose precision mid-chain).
 */
final class UomConversionService
{
    /** Output scale: matches StockItem::SCALE / DECIMAL(10,3) columns. */
    private const SCALE = 3;

    /** Intermediate scale for factor multiplication/division. */
    private const INTERMEDIATE_SCALE = 6;

    /**
     * @param  string  $quantity  numeric string at any precision, e.g. '1.500'
     *
     * @throws IncompatibleUomException when $from and $to have different types
     * @throws InvalidArgumentException when $quantity is not numeric or negative
     */
    public function convert(string $quantity, UnitOfMeasure $from, UnitOfMeasure $to): string
    {
        if (! is_numeric($quantity)) {
            throw new InvalidArgumentException("Quantity \"{$quantity}\" is not numeric.");
        }

        if (bccomp($quantity, '0', self::INTERMEDIATE_SCALE) === -1) {
            throw new InvalidArgumentException('Stock quantities cannot be negative.');
        }

        $fromType = $this->typeValue($from);
        $toType = $this->typeValue($to);

        if ($fromType !== $toType) {
            throw new IncompatibleUomException(
                "Cannot convert between incompatible unit types: {$fromType} ({$from->code}) to {$toType} ({$to->code})."
            );
        }

        // Same unit (or same code on the same tenant): identity.
        if ($from->id === $to->id) {
            return $this->quantize($quantity);
        }

        // quantity × from.factor = base units; base ÷ to.factor = target.
        $base = bcmul($quantity, (string) $from->base_conversion_factor, self::INTERMEDIATE_SCALE);

        $factor = (string) $to->base_conversion_factor;

        if (bccomp($factor, '0', self::INTERMEDIATE_SCALE) === 0) {
            throw new IncompatibleUomException(
                "Unit \"{$to->code}\" has a zero base conversion factor and cannot be converted to."
            );
        }

        return bcdiv($base, $factor, self::SCALE);
    }

    private function quantize(string $quantity): string
    {
        return bcadd($quantity, '0', self::SCALE);
    }

    private function typeValue(UnitOfMeasure $uom): string
    {
        /** @var mixed $raw */
        $raw = $uom->getAttribute('type');

        return $raw instanceof UomType ? $raw->value : (string) $raw;
    }
}
