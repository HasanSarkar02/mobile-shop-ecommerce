<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SessionRevocationService
{
    public function revoke(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
