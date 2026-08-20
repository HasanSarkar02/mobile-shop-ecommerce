<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\OwnerInvitationService;
use App\Services\OwnershipTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{tenant: Tenant, primary: User, target: User, admin: User} */
function ownershipTransferFixture(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'transfer-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
    $primary = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true]);
    $target = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff', 'is_active' => true]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $plan = Plan::query()->create([
        'name' => 'Transfer Plan '.Str::random(8),
        'slug' => 'transfer-plan-'.Str::lower(Str::random(8)),
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => true,
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    $tenant->forceFill(['primary_owner_id' => $primary->id])->save();

    return compact('tenant', 'primary', 'target', 'admin');
}

it('issues a transfer invitation with the expected previous primary owner', function (): void {
    ['tenant' => $tenant, 'primary' => $primary, 'target' => $target, 'admin' => $admin] = ownershipTransferFixture();

    $issued = app(OwnershipTransferService::class)->start($tenant, $target, $admin);
    $invitation = $issued['invitation']->fresh();

    expect($invitation->purpose)->toBe(TenantInvitation::PURPOSE_OWNER_TRANSFER)
        ->and($invitation->previous_primary_owner_id)->toBe($primary->id)
        ->and($invitation->user_id)->toBe($target->id);
});

it('accepts ownership transfer atomically and keeps the previous owner active', function (): void {
    ['tenant' => $tenant, 'primary' => $primary, 'target' => $target, 'admin' => $admin] = ownershipTransferFixture();
    $issued = app(OwnershipTransferService::class)->start($tenant, $target, $admin);

    $accepted = app(OwnerInvitationService::class)->acceptTransferToken($tenant, $issued['token'], 'transfer-secret-123');

    expect($accepted->id)->toBe($target->id)
        ->and($tenant->fresh()->primary_owner_id)->toBe($target->id)
        ->and($target->fresh()->role)->toBe('owner')
        ->and($target->fresh()->is_active)->toBeTrue()
        ->and($primary->fresh()->role)->toBe('owner')
        ->and($primary->fresh()->is_active)->toBeTrue();
});

it('accepts a transfer through the owner invitation setup route', function (): void {
    ['tenant' => $tenant, 'primary' => $primary, 'target' => $target, 'admin' => $admin] = ownershipTransferFixture();
    $issued = app(OwnershipTransferService::class)->start($tenant, $target, $admin);
    $url = 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/owner-invitation/'.$issued['token'];

    $this->get($url)->assertOk()->assertSee('Accept ownership transfer');
    $this->post($url, [
        'password' => 'transfer-secret-123',
        'password_confirmation' => 'transfer-secret-123',
    ])->assertRedirect('/admin');

    expect($tenant->fresh()->primary_owner_id)->toBe($target->id)
        ->and($primary->fresh()->is_active)->toBeTrue()
        ->and($target->fresh()->role)->toBe('owner');
});

it('rejects stale and cross-tenant ownership transfers', function (): void {
    ['tenant' => $tenant, 'primary' => $primary, 'target' => $target, 'admin' => $admin] = ownershipTransferFixture();
    $issued = app(OwnershipTransferService::class)->start($tenant, $target, $admin);
    $replacement = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true]);
    $tenant->forceFill(['primary_owner_id' => $replacement->id])->save();

    expect(fn () => app(OwnerInvitationService::class)->acceptTransferToken($tenant, $issued['token'], 'transfer-secret-123'))
        ->toThrow(DomainException::class)
        ->and($tenant->fresh()->primary_owner_id)->toBe($replacement->id)
        ->and($primary->fresh()->role)->toBe('owner');

    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'staff', 'is_active' => true]);

    expect(fn () => app(OwnershipTransferService::class)->start($tenant, $otherUser, $admin))
        ->toThrow(DomainException::class);
});

it('rolls back target promotion and primary-owner change when acceptance fails', function (): void {
    ['tenant' => $tenant, 'primary' => $primary, 'target' => $target, 'admin' => $admin] = ownershipTransferFixture();
    $issued = app(OwnershipTransferService::class)->start($tenant, $target, $admin);
    $shouldFail = true;

    DB::listen(function ($query) use (&$shouldFail): void {
        if ($shouldFail
            && str_contains(strtolower($query->sql), 'update')
            && str_contains(strtolower($query->sql), 'tenant_invitations')) {
            $shouldFail = false;
            throw new RuntimeException('Simulated transfer invitation update failure.');
        }
    });

    expect(fn () => app(OwnerInvitationService::class)->acceptTransferToken($tenant, $issued['token'], 'transfer-secret-123'))
        ->toThrow(RuntimeException::class);

    expect($tenant->fresh()->primary_owner_id)->toBe($primary->id)
        ->and($target->fresh()->role)->toBe('staff')
        ->and($issued['invitation']->fresh()->consumed_at)->toBeNull();
});
