<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Redirect;

class RedirectService
{
    private const PATH_PREFIXES = [
        'product' => '/product/',
        'category' => '/category/',
        'brand' => '/brand/',
        'collection' => '/collection/',
        'static_page' => '/page/',
        'blog_post' => '/blog/',
    ];

    public function recordSlugChange(string $sourceType, int $sourceId, ?string $oldSlug, ?string $newSlug): void
    {
        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug || ! isset(self::PATH_PREFIXES[$sourceType])) {
            return;
        }

        $prefix = self::PATH_PREFIXES[$sourceType];
        $fromPath = $prefix.$oldSlug;
        $toPath = $prefix.$newSlug;

        // Prevent redirect chains/loops when a slug is reverted back to a previously redirected-from value.
        Redirect::query()->where('to_path', $fromPath)->delete();

        Redirect::query()->updateOrCreate(
            ['tenant_id' => tenant()?->id, 'from_path' => $fromPath],
            ['to_path' => $toPath, 'status_code' => 301, 'source_type' => $sourceType, 'source_id' => $sourceId],
        );
    }
}