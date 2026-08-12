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