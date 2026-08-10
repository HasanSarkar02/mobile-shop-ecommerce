<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;

class RobotsController extends Controller
{
    public function index()
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /wishlist',
            'Disallow: /compare',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        if ($extra = tenant()->settings->robots_txt_extra) {
            $lines[] = $extra;
        }

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }
}