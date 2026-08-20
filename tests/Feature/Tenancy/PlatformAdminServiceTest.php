<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Models\PlatformAdminInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PlatformAdminInvitationNotification;
use App\Services\PlatformAdminService;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

function platformAdminActor(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'is_active' => true]);
}

/** @return array{user: User, invitation: PlatformAdminInvitation, token: string, actor: User} */
function pendingPlatformAdminInvitation(): array
{
    $actor = platformAdminActor();
    $result = app(PlatformAdminService::class)->invite('Pending Admin', fake()->unique()->safeEmail(), $actor);

    return [...$result, 'actor' => $actor];
}

function insertSessionFor(User $user): void
{
    DB::table('sessions')->insert([
        'id' => 'session-'.Str::random(20),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
}

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    app(Tenancy::class)->set(null);
});

it('creates a pending platform admin invitation with a digest-only token', function (): void {
    Notification::fake();
    $actor = platformAdminActor();

    $result = app(PlatformAdminService::class)->invite('New Admin', 'new@admin.test', $actor);

    expect($result['user']->is_platform_admin)->toBeTrue()
        ->and($result['user']->is_active)->toBeFalse()
        ->and($result['user']->tenant_id)->toBeNull()
        ->and($result['user']->role)->toBeNull()
        ->and($result['invitation']->token_digest)->toBe(hash('sha256', $result['token']))
        ->and($result['invitation']->token_digest)->not->toBe($result['token']);

    $this->assertDatabaseMissing('platform_admin_invitations', ['token_digest' => $result['token']]);
    Notification::assertSentTo($result['user'], PlatformAdminInvitationNotification::class, function (PlatformAdminInvitationNotification $notification) use ($result): bool {
        return $notification->token === $result['token'];
    });
});

it('rejects duplicate emails that are not eligible for re-invite', function (): void {
    $actor = platformAdminActor();
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['email' => 'owner@dup.test', 'tenant_id' => $tenant->id, 'role' => 'owner']);
    $existingAdmin = User::factory()->create(['email' => 'admin@dup.test', 'is_platform_admin' => true]);
    $service = app(PlatformAdminService::class);

    expect(fn () => $service->invite('Owner', $owner->email, $actor))->toThrow(DomainException::class)
        ->and(fn () => $service->invite('Admin', $existingAdmin->email, $actor))->toThrow(DomainException::class);
});

it('re-invites an existing unassigned non-admin user', function (): void {
    Notification::fake();
    $actor = platformAdminActor();
    $existing = User::factory()->create(['email' => 'reinvite@admin.test']);

    $result = app(PlatformAdminService::class)->invite('Reinvite', 'reinvite@admin.test', $actor);

    expect($result['user']->is($existing))->toBeTrue()
        ->and($result['user']->is_platform_admin)->toBeTrue()
        ->and($result['user']->is_active)->toBeFalse();
});

it('enforces the actor boundary for invites', function (): void {
    $plain = User::factory()->create();
    $inactiveAdmin = User::factory()->create(['is_platform_admin' => true, 'is_active' => false]);
    $actor = platformAdminActor();
    $service = app(PlatformAdminService::class);

    expect(fn () => $service->invite('Plain', 'p@admin.test', $plain))->toThrow(DomainException::class)
        ->and(fn () => $service->invite('Inactive', 'i@admin.test', $inactiveAdmin))->toThrow(DomainException::class);

    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    expect(fn () => $service->invite('Dedicated', 'd@admin.test', $actor))->toThrow(DomainException::class);
});

it('accepts a valid invitation and finalizes the admin', function (): void {
    ['token' => $token, 'user' => $user, 'invitation' => $invitation] = pendingPlatformAdminInvitation();

    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');

    expect(Hash::check('StrongPass!123', $accepted->password))->toBeTrue()
        ->and($accepted->is_active)->toBeTrue()
        ->and($accepted->is_platform_admin)->toBeTrue()
        ->and($accepted->email_verified_at)->not->toBeNull()
        ->and($accepted->password_changed_at)->not->toBeNull()
        ->and($accepted->id)->toBe($user->id);

    expect($invitation->refresh()->accepted_at)->not->toBeNull()
        ->and($invitation->consumed_at)->not->toBeNull();
});

it('rejects expired, revoked, consumed and bogus tokens', function (): void {
    $service = app(PlatformAdminService::class);

    $expired = pendingPlatformAdminInvitation();
    $expired['invitation']->update(['expires_at' => now()->subMinute()]);
    expect(fn () => $service->acceptInvitation($expired['token'], 'StrongPass!123'))->toThrow(DomainException::class);

    $revoked = pendingPlatformAdminInvitation();
    $revoked['invitation']->update(['revoked_at' => now()]);
    expect(fn () => $service->acceptInvitation($revoked['token'], 'StrongPass!123'))->toThrow(DomainException::class);

    $consumed = pendingPlatformAdminInvitation();
    $consumed['invitation']->update(['accepted_at' => now(), 'consumed_at' => now()]);
    expect(fn () => $service->acceptInvitation($consumed['token'], 'StrongPass!123'))->toThrow(DomainException::class);

    expect(fn () => $service->acceptInvitation('bogus-token', 'StrongPass!123'))->toThrow(DomainException::class);
});

it('does not activate a pending admin who has not completed setup', function (): void {
    ['user' => $pending, 'actor' => $actor] = pendingPlatformAdminInvitation();

    expect(fn () => app(PlatformAdminService::class)->activate($pending, $actor))->toThrow(DomainException::class);
});

it('activates, deactivates and reactivates an admin with session revocation', function (): void {
    ['token' => $token, 'user' => $user, 'actor' => $actor] = pendingPlatformAdminInvitation();
    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');
    insertSessionFor($accepted);
    $service = app(PlatformAdminService::class);

    $deactivated = $service->deactivate($accepted, $actor);

    expect($deactivated->is_active)->toBeFalse()
        ->and($deactivated->deactivated_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $accepted->id)->count())->toBe(0)
        ->and($deactivated->remember_token)->not->toBeNull();

    $reactivated = $service->activate($accepted->fresh(), $actor);

    expect($reactivated->is_active)->toBeTrue()
        ->and($reactivated->deactivated_at)->toBeNull()
        ->and($reactivated->password_changed_at)->not->toBeNull();
});

it('protects the last active Platform Admin', function (): void {
    $solo = platformAdminActor();
    $service = app(PlatformAdminService::class);

    expect(fn () => $service->deactivate($solo, $solo))->toThrow(DomainException::class)
        ->and(fn () => $service->revoke($solo, $solo))->toThrow(DomainException::class);
});

it('revokes platform privilege and revokes sessions', function (): void {
    ['token' => $token, 'user' => $user, 'actor' => $actor] = pendingPlatformAdminInvitation();
    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');
    insertSessionFor($accepted);

    $revoked = app(PlatformAdminService::class)->revoke($accepted, $actor);

    expect($revoked->is_platform_admin)->toBeFalse()
        ->and($revoked->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $accepted->id)->count())->toBe(0)
        ->and($revoked->canAccessPanel(Filament::getPanel('platform')))->toBeFalse();
});

it('resets a password through a fresh setup link and revokes sessions', function (): void {
    Notification::fake();
    ['token' => $token, 'user' => $user, 'actor' => $actor] = pendingPlatformAdminInvitation();
    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');
    insertSessionFor($accepted);

    $issued = app(PlatformAdminService::class)->resetPassword($accepted, $actor);

    expect(DB::table('sessions')->where('user_id', $accepted->id)->count())->toBe(0)
        ->and($issued['invitation']->token_digest)->toBe(hash('sha256', $issued['token']))
        ->and($accepted->fresh()->is_active)->toBeTrue();

    Notification::assertSentTo($accepted, PlatformAdminInvitationNotification::class);
});

it('resets MFA state and revokes sessions', function (): void {
    ['token' => $token, 'user' => $user, 'actor' => $actor] = pendingPlatformAdminInvitation();
    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');
    $accepted->forceFill([
        'app_authentication_secret' => 'secret',
        'app_authentication_recovery_codes' => ['code-a', 'code-b'],
    ])->save();
    insertSessionFor($accepted);

    $reset = app(PlatformAdminService::class)->resetMfa($accepted, $actor);

    expect($reset->app_authentication_secret)->toBeNull()
        ->and($reset->app_authentication_recovery_codes)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $accepted->id)->count())->toBe(0);
});

it('grants platform privilege to an unassigned non-admin user', function (): void {
    $actor = platformAdminActor();
    $candidate = User::factory()->create();

    $granted = app(PlatformAdminService::class)->grant($candidate, $actor);

    expect($granted->is_platform_admin)->toBeTrue()
        ->and($granted->is_active)->toBeTrue();
});

it('rejects tenant users, owners, staff and existing admins from grant', function (): void {
    $actor = platformAdminActor();
    $tenant = Tenant::factory()->create();
    $service = app(PlatformAdminService::class);

    $tenantUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
    $existing = platformAdminActor();

    expect(fn () => $service->grant($tenantUser, $actor))->toThrow(DomainException::class)
        ->and(fn () => $service->grant($owner, $actor))->toThrow(DomainException::class)
        ->and(fn () => $service->grant($staff, $actor))->toThrow(DomainException::class)
        ->and(fn () => $service->grant($existing, $actor))->toThrow(DomainException::class);
});

it('denies platform panel access until MFA is enrolled', function (): void {
    $admin = User::factory()->create(['is_platform_admin' => true, 'is_active' => true]);
    Auth::guard('platform')->login($admin);

    $this->get('http://'.config('tenancy.central_domain').'/platform/platform-admins')
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('allows platform panel access once MFA is enrolled', function (): void {
    $admin = User::factory()->create([
        'is_platform_admin' => true,
        'is_active' => true,
        'app_authentication_secret' => 'test-secret',
    ]);
    Auth::guard('platform')->login($admin);

    $this->get('http://'.config('tenancy.central_domain').'/platform/platform-admins')
        ->assertSuccessful();
});

it('requires MFA re-enrollment after an MFA reset', function (): void {
    ['token' => $token, 'user' => $user, 'actor' => $actor] = pendingPlatformAdminInvitation();
    $accepted = app(PlatformAdminService::class)->acceptInvitation($token, 'StrongPass!123');
    $accepted->forceFill(['app_authentication_secret' => 'secret'])->save();

    $reset = app(PlatformAdminService::class)->resetMfa($accepted, $actor);

    expect($reset->app_authentication_secret)->toBeNull();
});

it('renders and accepts the invitation over HTTP on the central host', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $actor = platformAdminActor();
    $result = app(PlatformAdminService::class)->invite('Web Admin', 'web@admin.test', $actor);

    $this->get('http://'.config('tenancy.central_domain').'/platform-admin-invitation/'.$result['token'])
        ->assertOk()
        ->assertSee('Set up Platform Admin access');

    $this->post('http://'.config('tenancy.central_domain').'/platform-admin-invitation/'.$result['token'], [
        'password' => 'StrongPass!123',
        'password_confirmation' => 'StrongPass!123',
    ])->assertRedirect('/platform');

    $this->assertAuthenticatedAs($result['user'], 'platform');
    expect($result['user']->fresh()->is_active)->toBeTrue();
});

it('validates password rules and returns 410 for an already-used token', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $actor = platformAdminActor();
    $result = app(PlatformAdminService::class)->invite('Web Admin', 'web2@admin.test', $actor);
    $base = 'http://'.config('tenancy.central_domain').'/platform-admin-invitation/'.$result['token'];

    $this->post($base, [
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->post($base, [
        'password' => 'StrongPass!123',
        'password_confirmation' => 'StrongPass!123',
    ])->assertRedirect('/platform');

    $this->post($base, [
        'password' => 'StrongPass!123',
        'password_confirmation' => 'StrongPass!123',
    ])->assertStatus(410);
});

it('does not mass-assign privileged fields while factory fixtures still work', function (): void {
    $tenant = Tenant::factory()->create();

    $user = User::query()->create([
        'name' => 'Rashid',
        'email' => 'rashid@example.test',
        'password' => Hash::make('secret1234'),
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'is_platform_admin' => true,
        'is_active' => false,
    ]);

    $fresh = $user->fresh();

    expect($fresh->tenant_id)->toBeNull()
        ->and($fresh->role)->toBeNull()
        ->and($fresh->is_platform_admin)->toBeFalse()
        ->and($fresh->is_active)->toBeTrue();

    $fixture = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'is_platform_admin' => true,
        'is_active' => false,
    ]);

    expect($fixture->tenant_id)->toBe($tenant->id)
        ->and($fixture->role)->toBe('owner')
        ->and($fixture->is_platform_admin)->toBeTrue()
        ->and($fixture->is_active)->toBeFalse();
});

it('keeps raw tokens and passwords out of the audit trail', function (): void {
    $actor = platformAdminActor();
    $result = app(PlatformAdminService::class)->invite('Audit', 'audit@admin.test', $actor);
    app(PlatformAdminService::class)->acceptInvitation($result['token'], 'StrongPass!123');

    $payload = Activity::query()
        ->where('log_name', 'platform-admins')
        ->get()
        ->pluck('properties')
        ->toJson();

    expect($payload)->not->toContain($result['token'])
        ->and($payload)->not->toContain('StrongPass!123');
});
