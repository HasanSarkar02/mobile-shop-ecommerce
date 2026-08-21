<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Brand;
use App\Services\RedirectService;

class BrandObserver
{
    public function __construct(private readonly RedirectService $redirects) {}

    public function updated(Brand $brand): void
    {
        if ($brand->wasChanged('slug')) {
            $this->redirects->recordSlugChange('brand', $brand->id, $brand->getOriginal('slug'), $brand->slug);
        }
    }
}
