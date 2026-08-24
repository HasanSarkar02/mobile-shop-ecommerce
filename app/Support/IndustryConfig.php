<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Single source of truth for industry composition/config presets (Phase F.5).
 *
 * Resolves a tenant's industry code against `config/industries.php`. Every
 * preset declares only deltas from the `general` baseline, and this class
 * merges them — so an unknown, null, or missing industry always degrades to
 * the safe `general` preset and unspecified keys always yield general's
 * defaults. Nothing here renders UI; primitives consume resolved values.
 */
final class IndustryConfig
{
    /**
     * All registered industry codes (the `general` fallback included).
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys((array) config('industries.presets', []));
    }

    /**
     * The fully-merged preset for an industry. Unknown/null industries resolve
     * to `general` (or whatever `industries.default` names), so callers never
     * need their own fallback logic.
     *
     * @return array<string, mixed>
     */
    public static function resolve(?string $industry): array
    {
        $code = self::normalize($industry);

        $preset = (array) config("industries.presets.{$code}", []);
        $base = (array) config('industries.presets.general', []);

        // Deltas win; everything else inherits from general.
        return array_replace_recursive($base, $preset);
    }

    /**
     * Dot-notation lookup inside a resolved preset,
     * e.g. get('fashion', 'pdp.layout') or get(null, 'card.hover_gallery_enabled').
     */
    public static function get(?string $industry, string $key, mixed $default = null): mixed
    {
        return Arr::get(self::resolve($industry), $key, $default);
    }

    private static function normalize(?string $industry): string
    {
        $code = trim((string) $industry);

        $codes = self::codes();

        if ($code !== '' && in_array($code, $codes, true)) {
            return $code;
        }

        $fallback = (string) config('industries.default', 'general');

        return in_array($fallback, $codes, true) ? $fallback : 'general';
    }
}
