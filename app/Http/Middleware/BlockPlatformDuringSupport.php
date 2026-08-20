<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockPlatformDuringSupport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has(ResolveSupportSession::SESSION_KEY)) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
