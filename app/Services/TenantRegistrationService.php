<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeTenantOwnerNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantRegistrationService
{
    /**
     * @return array{0: Tenant, 1: User}
     */
    public function register(string $businessName, string $subdomain, string $ownerName, string $ownerEmail, string $password): array
    {
        return DB::transaction(function () use ($businessName, $subdomain, $ownerName, $ownerEmail, $password): array {
            $tenant = Tenant::query()->create([
                'name' => $businessName,
                'subdomain' => strtolower($subdomain),
                'status' => 'trial',
                'plan' => 'trial',
                'currency' => 'BDT',
            ]);

            $trialPlan = \App\Models\Plan::query()->where('slug', 'trial')->firstOrFail();
            app(\App\Services\SubscriptionService::class)->startTrial($tenant, $trialPlan, (int) config('tenancy.trial_days'));

            $owner = User::query()->create([
                'tenant_id' => $tenant->id,
                'role' => 'owner',
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => Hash::make($password),
            ]);

            $owner->notify(new WelcomeTenantOwnerNotification($tenant));

            return [$tenant, $owner];
        });
    }
}