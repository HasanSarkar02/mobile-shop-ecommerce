<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(Tenancy::class)->get();
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * Small presentation mapping from the tenant's currency code to its
     * symbol. No rates, no formatting engine — just the glyph used by the
     * storefront price components. Unknown codes fall back to the code plus a
     * space so a value is always rendered.
     */
    function currency_symbol(?string $currency = null): string
    {
        $symbols = [
            'BDT' => '৳',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'PKR' => '₨',
        ];

        $currency ??= tenant()?->currency ?? 'BDT';

        return $symbols[$currency] ?? $currency.' ';
    }
}

if (! function_exists('media_alt')) {
    /**
     * Resolves an image's alt text from its media custom_properties, falling
     * back to a supplied default. Admin can set alt per image; product name is
     * used as the fallback on the PDP gallery.
     */
    function media_alt(Media $media, string $fallback = ''): string
    {
        $alt = $media->getCustomProperty('alt');

        return is_string($alt) && $alt !== '' ? $alt : $fallback;
    }
}
