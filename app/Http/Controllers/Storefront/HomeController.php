<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Support\Seo\SeoData;

class HomeController extends Controller
{
    public function __invoke()
    {
        $sections = HomepageSection::query()
            ->currentlyActive()
            ->orderBy('sort_order')
            ->get();

        $seo = SeoData::default();

        return view('storefront.home', compact('sections', 'seo'));
    }
}
