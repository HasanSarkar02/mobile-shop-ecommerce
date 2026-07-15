<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Renders {{ group.key }} placeholders, e.g. {{ order.number }}, {{ customer.name }},
 * {{ order.total }}, {{ store.name }}, {{ tracking.url }}.
 * Unmatched placeholders are left as empty strings, not errors — a template
 * referencing a not-yet-available field (e.g. tracking.url before Phase 6
 * builds order tracking) degrades gracefully rather than breaking delivery.
 */
final class PlaceholderRenderer
{
    public static function render(string $template, array $context): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $matches): string => (string) (Arr::get($context, "{$matches[1]}.{$matches[2]}") ?? ''),
            $template,
        );
    }
}