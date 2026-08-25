<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TenantIndustry;
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
    public static function resolve(TenantIndustry|string|null $industry): array
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
     * When $industry is null, the current tenant's industry is used.
     * Accepts TenantIndustry enum directly to avoid strict-typing collisions
     * after Tenant.industry was cast to enum in B-1.
     */
    public static function get(TenantIndustry|string|null $industry, string $key, mixed $default = null): mixed
    {
        return Arr::get(self::resolve($industry), $key, $default);
    }

    /**
     * Resolve for the current tenant (reads Tenant::industry, falls back to general).
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        return self::resolve(null);
    }

    /**
     * Get a value for the current tenant's industry.
     */
    public static function currentGet(string $key, mixed $default = null): mixed
    {
        return self::get(null, $key, $default);
    }

    private static function normalize(TenantIndustry|string|null $industry): string
    {
        // Direct enum pass-through (e.g. tenant()->industry from ProductCardData)
        // — extract the backing value before string normalization.
        if ($industry instanceof TenantIndustry) {
            $industry = $industry->value;
        }

        // When no explicit industry is given, use the current tenant's
        // industry (Phase B data layer). Falls back to general if no tenant
        // or tenant has no industry yet (pre-migration).
        if ($industry === null || trim($industry) === '') {
            /** @var mixed $tenantIndustry */
            $tenantIndustry = null;
            if (function_exists('tenant') && ($tenant = tenant()) !== null) {
                /** @var mixed $tenantIndustry */
                $tenantIndustry = $tenant->industry;

                if ($tenantIndustry instanceof TenantIndustry) {
                    $tenantIndustry = $tenantIndustry->value;
                }
            }

            if (is_string($tenantIndustry) && trim($tenantIndustry) !== '') {
                $industry = $tenantIndustry;
            }
        }

        $code = trim((string) $industry);

        $codes = self::codes();

        if ($code !== '' && in_array($code, $codes, true)) {
            return $code;
        }

        $fallback = (string) config('industries.default', 'general');

        return in_array($fallback, $codes, true) ? $fallback : 'general';
    }
}
