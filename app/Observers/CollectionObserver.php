<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Collection;
use App\Services\RedirectService;

class CollectionObserver
{
    public function __construct(private readonly RedirectService $redirects)
    {
    }

    public function updated(Collection $collection): void
    {
        if ($collection->wasChanged('slug')) {
            $this->redirects->recordSlugChange('collection', $collection->id, $collection->getOriginal('slug'), $collection->slug);
        }
    }
}