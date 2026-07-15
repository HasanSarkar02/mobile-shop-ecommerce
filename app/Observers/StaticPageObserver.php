<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\StaticPage;
use App\Services\RedirectService;

class StaticPageObserver
{
    public function __construct(private readonly RedirectService $redirects)
    {
    }

    public function updated(StaticPage $page): void
    {
        if ($page->wasChanged('slug')) {
            $this->redirects->recordSlugChange('static_page', $page->id, $page->getOriginal('slug'), $page->slug);
        }
    }
}