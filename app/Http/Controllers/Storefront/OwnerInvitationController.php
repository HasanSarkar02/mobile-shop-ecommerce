<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\OwnerInvitationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerInvitationController extends Controller
{
    public function show(string $token, OwnerInvitationService $invitations)
    {
        $tenant = $this->tenant();

        try {
            $invitation = $invitations->validateTokenForTenant($tenant, $token);
            $owner = User::query()->findOrFail($invitation->user_id);
            $invitations->markOpened($tenant, $owner, $token);
        } catch (DomainException $exception) {
            abort(410, $exception->getMessage());
        }

        return view('storefront.owner-invitation', [
            'tenant' => $tenant,
            'token' => $token,
            'expiresAt' => $invitation->expires_at,
            'isTransfer' => $invitation->purpose === TenantInvitation::PURPOSE_OWNER_TRANSFER,
        ]);
    }

    public function accept(Request $request, string $token, OwnerInvitationService $invitations)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $tenant = $this->tenant();

        try {
            $invitation = $invitations->validateTokenForTenant($tenant, $token);
            if ($invitation->purpose === TenantInvitation::PURPOSE_OWNER_TRANSFER) {
                $owner = $invitations->acceptTransferToken($tenant, $token, $validated['password']);
            } else {
                $owner = User::query()->findOrFail($invitation->user_id);
                $owner = $invitations->acceptToken($tenant, $owner, $token, $validated['password']);
            }
        } catch (DomainException $exception) {
            abort(410, $exception->getMessage());
        }

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
