<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const COOKIE = 'storefront_locale';

    public const COOKIE_TTL_MINUTES = 43200; // 30 days

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $enabled = $tenant !== null ? $tenant->enabledLocales() : ['en'];
        $preferred = $tenant !== null ? $tenant->preferredLocale() : 'en';

        $locale = null;
        $fromRoute = false;

        // URL prefix /bn/ wins when tenant supports it (see routes/tenant.php
        // prefix('bn') group). We detect via path, not route param, because
        // the prefix is a literal 'bn', not a {locale} param.
        if ($this->hasBnPrefix($request)) {
            if (in_array('bn', $enabled, true)) {
                $locale = 'bn';
                $fromRoute = true;
            } else {
                // Tenant does not support BN but URL has /bn/ — 404 per spec
                abort(404);
            }
        }

        if ($locale === null) {
            $locale = $this->resolveLocale($request, $enabled, $preferred);
        }

        App::setLocale($locale);
        view()->share('currentLocale', $locale);

        /** @var Response $response */
        $response = $next($request);

        // Do not overwrite the cookie that LocaleController just queued for POST /locale/{locale}
        // (otherwise SetLocale's stale bn cookie would win and switching bn→en would stay bn).
        if ($request->is('locale/*') || $request->is('bn/locale/*')) {
            $response->headers->set('Content-Language', $locale);

            return $response;
        }

        // Persist choice (cookie-only per Phase A). When locale came from URL,
        // always store it; otherwise store non-default or update stale cookie.
        if ($fromRoute) {
            Cookie::queue(
                Cookie::make(self::COOKIE, $locale, self::COOKIE_TTL_MINUTES, '/', null, false, false, false, 'Lax'),
            );
        } elseif ($locale !== 'en' && in_array($locale, $enabled, true)) {
            Cookie::queue(
                Cookie::make(self::COOKIE, $locale, self::COOKIE_TTL_MINUTES, '/', null, false, false, false, 'Lax'),
            );
        } elseif ($locale === 'en' && $request->cookie(self::COOKIE) !== null && $request->cookie(self::COOKIE) !== 'en') {
            Cookie::queue(
                Cookie::make(self::COOKIE, 'en', self::COOKIE_TTL_MINUTES, '/', null, false, false, false, 'Lax'),
            );
        }

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function hasBnPrefix(Request $request): bool
    {
        $path = $request->path();

        return $path === 'bn' || str_starts_with($path, 'bn/');
    }

    private function resolveLocale(Request $request, array $enabled, string $preferred): string
    {
        // 1) Cookie (30 days) — explicit user choice via /bn/ visit
        $cookieLocale = $request->cookie(self::COOKIE);
        if (is_string($cookieLocale)) {
            $cookieLocale = strtolower(trim($cookieLocale));
            if (in_array($cookieLocale, $enabled, true)) {
                return $cookieLocale;
            }
        }

        // 2) Browser Accept-Language (only bn is relevant)
        $accept = (string) $request->header('Accept-Language', '');
        if ($accept !== '' && in_array('bn', $enabled, true)) {
            if (preg_match('/\bbn\b/i', $accept) === 1) {
                return 'bn';
            }
        }

        // 3) Default to EN for explicit EN URLs (no /bn/ prefix). Tenant
        // preferred_locale is intentionally not used here — an EN URL without
        // prefix should stay EN, otherwise /product/en-slug would show BN
        // content when tenant prefers BN (see SetLocaleTest).
        return 'en';
    }
}
