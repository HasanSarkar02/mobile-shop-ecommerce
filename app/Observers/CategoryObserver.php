<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use App\Services\RedirectService;

class CategoryObserver
{
    public function __construct(private readonly RedirectService $redirects) {}

    public function updated(Category $category): void
    {
        if ($category->wasChanged('slug')) {
            $this->redirects->recordSlugChange('category', $category->id, $category->getOriginal('slug'), $category->slug);
        }
    }
}
