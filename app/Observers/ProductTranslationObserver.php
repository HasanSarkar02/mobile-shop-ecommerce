<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductTranslation;
use App\Services\RedirectService;

class ProductTranslationObserver
{
    public function __construct(private readonly RedirectService $redirects) {}

    public function updated(ProductTranslation $translation): void
    {
        if ($translation->wasChanged('slug')) {
            $this->redirects->recordSlugChange('product', $translation->product_id, $translation->getOriginal('slug'), $translation->slug);
        }
    }
}
