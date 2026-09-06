<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $locale = strtolower($locale);

        if (! in_array($locale, ['en', 'bn'], true)) {
            abort(404);
        }

        $tenant = tenant();

        if ($tenant !== null && ! $tenant->supportsLocale($locale)) {
            abort(404);
        }

        // Persist choice for 30 days, mirrors SetLocale::COOKIE_TTL_MINUTES
        Cookie::queue(Cookie::make('storefront_locale', $locale, 43200, null, null, false, false, false, 'lax'));

        $referer = $request->header('referer');
        $redirectTo = $request->input('redirect_to', $referer);

        if (! is_string($redirectTo) || $redirectTo === '') {
            $redirectTo = $request->header('referer');
        }

        if (! is_string($redirectTo) || $redirectTo === '') {
            // Fallback to storefront home
            $path = '/';
        } else {
            $path = parse_url($redirectTo, PHP_URL_PATH) ?? '/';
            $query = parse_url($redirectTo, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                $path .= '?'.$query;
            }
        }

        // Strip existing /bn prefix if present
        $pathWithoutPrefix = $path;
        if ($path === '/bn' || $path === '/bn/') {
            $pathWithoutPrefix = '/';
        } elseif (str_starts_with($path, '/bn/')) {
            $pathWithoutPrefix = substr($path, 3) ?: '/';
        }

        // Query string handling: keep query with pathWithoutPrefix already includes it
        // For redirect, we need path with query
        $targetPath = $pathWithoutPrefix;
        if ($locale === 'bn') {
            $targetPath = '/bn'.($pathWithoutPrefix === '/' ? '' : $pathWithoutPrefix);
        }

        // Keep same host by redirecting to path only
        return redirect($targetPath);
    }
}
