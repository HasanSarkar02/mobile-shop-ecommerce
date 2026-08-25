<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use App\Support\ThemePresets;
use Illuminate\Support\Number;
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

if (! function_exists('money')) {
    /**
     * Format an integer minor-unit amount (e.g. 120000 = ৳1,200.00) with
     * locale-aware grouping via Number::format but Western numerals (Chaldal/
     * Daraz). Currency symbol via currency_symbol(). Keeps int contract.
     */
    function money(int $minor, ?string $currency = null, ?string $locale = null, bool $withTrailingZeros = true): string
    {
        $locale ??= app()->getLocale();
        // Use en-BD grouping for both en and bn to keep Western numerals;
        // bn-BD would emit Bengali digits which we then map back.
        $localeTag = $locale === 'bn' ? 'bn-BD' : 'en-BD';
        $symbol = currency_symbol($currency);
        $major = $minor / 100;

        $formatted = Number::format($major, precision: $withTrailingZeros ? 2 : 0, locale: $localeTag);

        // Force Western numerals even when locale is bn-BD
        $formatted = strtr($formatted, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);

        return $symbol.$formatted;
    }
}

if (! function_exists('money_without_trailing_zeros')) {
    function money_without_trailing_zeros(int $minor, ?string $currency = null, ?string $locale = null): string
    {
        return money($minor, $currency, $locale, false);
    }
}

if (! function_exists('theme_font_stack')) {
    /**
     * Resolve a font_family key (inter|poppins|roboto) to a CSS font-stack.
     * Backward-compatible: null/unknown falls back to Instrument Sans + Hind Siliguri.
     */
    function theme_font_stack(?string $fontFamily): string
    {
        return ThemePresets::fontStack($fontFamily);
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
