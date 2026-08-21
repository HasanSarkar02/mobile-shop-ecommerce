<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoLoginController extends Controller
{
    public function __invoke(Request $request, int $user)
    {
        abort_unless($request->hasValidSignature(false), 403);

        $owner = User::query()->where('tenant_id', tenant()->id)->findOrFail($user);

        Auth::guard('web')->login($owner);

        return redirect('/admin');
    }
}
