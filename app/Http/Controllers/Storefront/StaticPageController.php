<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;

class StaticPageController extends Controller
{
    public function show(string $slug)
    {
        $page = StaticPage::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('storefront.pages.show', compact('page'));
    }
}