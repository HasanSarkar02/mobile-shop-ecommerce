<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\RedirectService;

class BlogPostObserver
{
    public function __construct(private readonly RedirectService $redirects) {}

    public function updated(BlogPost $post): void
    {
        if ($post->wasChanged('slug')) {
            $this->redirects->recordSlugChange('blog_post', $post->id, $post->getOriginal('slug'), $post->slug);
        }
    }
}
