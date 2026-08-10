<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignStorefrontTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->cookie('cart_token')) {
            Cookie::queue('cart_token', (string) Str::uuid(), 60 * 24 * (int) config('catalog.cart_token_days'));
        }

        if (! $request->cookie('wishlist_token')) {
            Cookie::queue('wishlist_token', (string) Str::uuid(), 60 * 24 * (int) config('catalog.wishlist_token_days'));
        }

        return $next($request);
    }
}