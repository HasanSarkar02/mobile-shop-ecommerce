<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-industry brand palette presets (Commit B-3).
 *
 * Single source of truth for primary/secondary hex codes seeded per tenant.
 * Secondary is kept nullable in DB but seeded here so out-of-the-box UI is
 * finished without owner intervention. Fallback is `general`.
 */
final class ThemePresets
{
    /**
     * @var array<string, array{primary: string, secondary: string}>
     */
    public const PRESETS = [
        'general' => ['primary' => '#16a34a', 'secondary' => '#15803d'],
        'electronics' => ['primary' => '#2563eb', 'secondary' => '#1d4ed8'],
        'mobile' => ['primary' => '#2563eb', 'secondary' => '#1d4ed8'],
        'furniture' => ['primary' => '#78350f', 'secondary' => '#451a03'],
        'grocery' => ['primary' => '#22c55e', 'secondary' => '#15803d'],
        'fashion' => ['primary' => '#111827', 'secondary' => '#000000'],
        'sports' => ['primary' => '#dc2626', 'secondary' => '#991b1b'],
    ];

    public const DEFAULT = 'general';

    /**
     * @return array{primary: string, secondary: string}
     */
    public static function forIndustry(?string $code): array
    {
        $key = strtolower(trim((string) $code));

        return self::PRESETS[$key] ?? self::PRESETS[self::DEFAULT];
    }

    public static function primaryFor(?string $code): string
    {
        return self::forIndustry($code)['primary'];
    }

    public static function secondaryFor(?string $code): string
    {
        return self::forIndustry($code)['secondary'];
    }

    /**
     * Map stored font_family keys to CSS font-stack values.
     */
    public static function fontStack(?string $fontFamily): string
    {
        return match (strtolower(trim((string) $fontFamily))) {
            'poppins' => "'Poppins', 'Hind Siliguri', ui-sans-serif, system-ui, sans-serif",
            'roboto' => "'Roboto', 'Hind Siliguri', ui-sans-serif, system-ui, sans-serif",
            default => "'Instrument Sans', 'Hind Siliguri', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
        };
    }
}
