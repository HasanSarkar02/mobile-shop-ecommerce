<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;

class HomeController extends Controller
{
    public function __invoke()
    {
        $sections = HomepageSection::query()
            ->currentlyActive()
            ->get();

        return view('storefront.home', compact('sections'));
    }
}
