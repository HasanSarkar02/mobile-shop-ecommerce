<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveSupportSession
{
    public const SESSION_KEY = 'support_mode';

    public const IDLE_TTL_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('support.exit')) {
            return $next($request);
        }

        $payload = session(self::SESSION_KEY);

        if (! is_array($payload)) {
            return $next($request);
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (! is_string($expiresAt)) {
            session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        if (Carbon::parse($expiresAt)->isPast()) {
            $this->logExpiration($payload);
            session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        $isWriteEnabled = ($payload['is_write_enabled'] ?? false) === true;

        if (! $isWriteEnabled && ! in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'Read-only Support Mode');
        }

        $tenantId = $payload['tenant_id'] ?? null;

        if (! is_int($tenantId) && ! (is_string($tenantId) && ctype_digit($tenantId))) {
            session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        $tenant = Tenant::query()->find((int) $tenantId);

        if (! $tenant instanceof Tenant) {
            session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        app(Tenancy::class)->set($tenant);

        $admin = auth('platform')->user();

        if ($admin instanceof User && $admin->getAttribute('is_platform_admin') === true) {
            Auth::guard('web')->login($admin);
        }

        $payload['expires_at'] = now()->addMinutes(self::IDLE_TTL_MINUTES)->toDateTimeString();

        session([self::SESSION_KEY => $payload]);

        return $next($request);
    }

    private function logExpiration(array $payload): void
    {
        $tenantId = $payload['tenant_id'] ?? null;

        if (! is_int($tenantId) && ! (is_string($tenantId) && ctype_digit($tenantId))) {
            return;
        }

        $tenant = Tenant::query()->find((int) $tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $actor = auth('platform')->user();

        activity('support')
            ->performedOn($tenant)
            ->causedBy($actor instanceof User ? $actor : null)
            ->event('support.mode_ended')
            ->withProperties([
                'support_session_id' => (string) ($payload['id'] ?? ''),
                'tenant_id' => (int) $tenantId,
                'entered_by_user_id' => (int) ($payload['entered_by_user_id'] ?? 0),
                'exit_type' => 'expired',
            ])
            ->log('support.mode_ended');
    }
}
