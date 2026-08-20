<?php

declare(strict_types=1);

namespace App\Filament\Platform\Support;

/**
 * BDT-only money presenter for the platform panel. Amounts are stored as
 * integer minor units (paisa) and rendered as major units with the taka
 * glyph, e.g. 99000 -> ৳990.00. Minor units are never shown raw.
 */
final class PlatformMoney
{
    public static function format(int $minorUnits): string
    {
        return '৳'.number_format($minorUnits / 100, 2);
    }
}
