<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HomepageSectionType;
use App\Models\HomepageSection;

final class HomepagePresentation
{
    /**
     * Resolve presentation for a homepage section.
     *
     * @return array{rows: int}
     */
    public static function resolve(HomepageSection $section): array
    {
        return ['rows' => self::rows($section)];
    }

    public static function rows(HomepageSection $section): int
    {
        $raw = $section->config['presentation']['rows'] ?? null;

        if (in_array($raw, [1, 2], true)) {
            return (int) $raw;
        }

        // Industry preset, split by category_rows vs product_rows per spec.
        // CategoryGrid with source !== 'brand' (i.e. category mode) uses category_rows,
        // both ProductGrid and CategoryGrid(brand) share product_rows.
        $isCategoryGrid = $section->type === HomepageSectionType::CategoryGrid;
        $source = $section->config['source'] ?? null;
        $isCategoryMode = $isCategoryGrid && $source !== 'brand';

        $industryKey = $isCategoryMode
            ? 'homepage.presentation.category_rows'
            : 'homepage.presentation.product_rows';

        $preset = IndustryConfig::currentGet($industryKey, null);

        if (in_array($preset, [1, 2], true)) {
            return (int) $preset;
        }

        return 1;
    }
}
