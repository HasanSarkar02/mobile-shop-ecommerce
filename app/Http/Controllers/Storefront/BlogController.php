<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;

class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return view('storefront.blog.index', compact('posts'));
    }

    public function show(string $slug)
    {
        $post = BlogPost::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('storefront.blog.show', compact('post'));
    }
}
