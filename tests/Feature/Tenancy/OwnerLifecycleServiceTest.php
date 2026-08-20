<?php

declare(strict_types=1);

use App\Filament\Platform\Resources\TenantResource\RelationManagers\OwnersRelationManager;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\OwnerLifecycleService;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\DB;

/** @return array{tenant: Tenant, owner: User, additional: User, admin: User} */
function ownerLifecycleFixture(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'owner-life-'.fake()->unique()->numberBetween(1000, 9999),
        'status' => 'active',
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true]);
    $additional = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true]);
    $admin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);

    return compact('tenant', 'owner', 'additional', 'admin');
}

beforeEach(function (): void {
    config()->set('deployment.mode', 'saas');
    Filament::setCurrentPanel('platform');
    app(Tenancy::class)->set(null);
});

it('deactivates and reactivates an owner while preserving Store access boundaries', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'additional' => $additional, 'admin' => $admin] = ownerLifecycleFixture();
    $service = app(OwnerLifecycleService::class);
    app(Tenancy::class)->set($tenant);

    expect($owner->canAccessPanel(Filament::getPanel('store')))->toBeTrue();

    $deactivated = $service->deactivate($tenant, $owner, $admin);

    expect($deactivated->is_active)->toBeFalse()
        ->and($deactivated->deactivated_at)->not->toBeNull()
        ->and($deactivated->canAccessPanel(Filament::getPanel('store')))->toBeFalse();

    $reactivated = $service->activate($tenant, $owner->fresh(), $admin);

    expect($reactivated->is_active)->toBeTrue()
        ->and($reactivated->deactivated_at)->toBeNull()
        ->and($reactivated->canAccessPanel(Filament::getPanel('store')))->toBeTrue()
        ->and($additional->fresh()->is_active)->toBeTrue();
});

it('prevents deactivating the last active owner and rejects cross-tenant owners', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerLifecycleFixture();
    $service = app(OwnerLifecycleService::class);

    $service->deactivate($tenant, User::query()->whereKey($owner->id)->firstOrFail(), $admin);
    $remaining = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->where('is_active', true)->firstOrFail();

    expect(fn () => $service->deactivate($tenant, $remaining, $admin))
        ->toThrow(DomainException::class);

    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner', 'is_active' => true]);

    expect(fn () => $service->deactivate($tenant, $otherOwner, $admin))
        ->toThrow(DomainException::class);
});

it('resets an active owner through a setup invitation and revokes existing sessions', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerLifecycleFixture();
    DB::table('sessions')->insert([
        'id' => 'owner-session-'.fake()->unique()->numberBetween(1000, 9999),
        'user_id' => $owner->id,
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $oldRememberToken = $owner->remember_token;

    $issued = app(OwnerLifecycleService::class)->resetPassword($tenant, $owner, $admin);
    $invitation = $issued['invitation']->fresh();

    expect($invitation->source)->toBe(TenantInvitation::SOURCE_PASSWORD_RESET)
        ->and(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and($owner->fresh()->remember_token)->not->toBe($oldRememberToken)
        ->and($invitation->token_digest)->not->toBe($issued['token']);
});

it('registers the owner relation manager for Platform tenant details', function (): void {
    expect(Tenant::class)->toBeString()
        ->and((new ReflectionClass(OwnersRelationManager::class))->isSubclassOf(RelationManager::class))->toBeTrue();
});
