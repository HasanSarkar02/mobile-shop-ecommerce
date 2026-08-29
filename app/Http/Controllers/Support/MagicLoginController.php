<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveSupportSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MagicLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(403, 'Invalid support token.');
        }

        $cacheKey = 'support_magic:'.$token;
        $payload = Cache::get($cacheKey);

        if (! is_array($payload)) {
            abort(403, 'Support token expired or invalid.');
        }

        // One-time use
        Cache::forget($cacheKey);

        $tenantId = $payload['tenant_id'] ?? null;
        if (! is_int($tenantId) && ! (is_string($tenantId) && ctype_digit($tenantId))) {
            abort(403, 'Invalid support payload.');
        }

        $tenant = Tenant::query()->find((int) $tenantId);
        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        // Ensure the request host actually belongs to this tenant (subdomain or custom domain)
        $currentTenant = tenant();
        if ($currentTenant instanceof Tenant && (int) $currentTenant->getKey() !== (int) $tenant->getKey()) {
            abort(403, 'Tenant mismatch.');
        }

        $adminId = $payload['entered_by_user_id'] ?? null;
        $admin = null;
        if (is_int($adminId) || (is_string($adminId) && ctype_digit($adminId))) {
            $admin = User::query()->find((int) $adminId);
        }

        if (! $admin instanceof User || $admin->getAttribute('is_platform_admin') !== true) {
            abort(403, 'Invalid support admin.');
        }

        // Validate TTL (15 minutes) — cache already expires, but double-check expires_at if present
        $expiresAt = $payload['expires_at'] ?? null;
        if (is_string($expiresAt) && Carbon::parse($expiresAt)->isPast()) {
            abort(403, 'Support token expired.');
        }

        session()->put(ResolveSupportSession::SESSION_KEY, [
            'id' => $payload['id'] ?? (string) Str::uuid(),
            'tenant_id' => (int) $tenant->getKey(),
            'started_at' => $payload['started_at'] ?? now()->toDateTimeString(),
            'expires_at' => $payload['expires_at'] ?? now()->addMinutes(ResolveSupportSession::IDLE_TTL_MINUTES)->toDateTimeString(),
            'entered_by_user_id' => (int) $admin->getKey(),
            'reason' => $payload['reason'] ?? 'Support magic link',
            'is_write_enabled' => (bool) ($payload['is_write_enabled'] ?? false),
        ]);

        // Log platform admin into web guard for Filament store panel
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
