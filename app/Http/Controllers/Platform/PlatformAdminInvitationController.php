<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\PlatformAdminService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAdminInvitationController extends Controller
{
    public function show(string $token)
    {
        return view('platform.admin-invitation', ['token' => $token]);
    }

    public function accept(Request $request, string $token, PlatformAdminService $admins)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        try {
            $admin = $admins->acceptInvitation($token, $validated['password']);
        } catch (DomainException $exception) {
            abort(410, $exception->getMessage());
        }

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();

        return redirect('/platform');
    }
}
