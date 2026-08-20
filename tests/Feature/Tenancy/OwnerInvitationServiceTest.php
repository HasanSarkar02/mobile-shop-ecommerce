<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\OwnerInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/** @return array{tenant: Tenant, owner: User, admin: User} */
function ownerInvitationFixture(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'invite-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);
    $admin = User::factory()->create([
        'is_platform_admin' => true,
        'app_authentication_secret' => 'test-secret',
    ]);

    return compact('tenant', 'owner', 'admin');
}

it('stores only a digest and binds an invitation to its tenant and owner', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();

    ['invitation' => $invitation, 'token' => $token] = app(OwnerInvitationService::class)->issue(
        $tenant,
        $owner,
        TenantInvitation::SOURCE_PLATFORM,
        $admin,
    );

    expect($token)->not->toBe($invitation->token_digest)
        ->and($invitation->token_digest)->toBe(hash('sha256', $token))
        ->and($invitation->tenant_id)->toBe($tenant->id)
        ->and($invitation->user_id)->toBe($owner->id)
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and(app(OwnerInvitationService::class)->validateToken($tenant, $owner, $token)->is($invitation))->toBeTrue();
});

it('rejects expired, revoked, and cross-tenant invitation tokens', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();
    $service = app(OwnerInvitationService::class);
    ['invitation' => $expired, 'token' => $expiredToken] = $service->issue($tenant, $owner, invitedBy: $admin);
    $expired->update(['expires_at' => now()->subMinute()]);

    expect(fn () => $service->validateToken($tenant, $owner, $expiredToken))
        ->toThrow(DomainException::class);

    ['invitation' => $revoked, 'token' => $revokedToken] = $service->issue($tenant, $owner, invitedBy: $admin);
    $service->revoke($revoked, $admin);

    expect(fn () => $service->validateToken($tenant, $owner, $revokedToken))
        ->toThrow(DomainException::class);

    ['tenant' => $otherTenant, 'owner' => $otherOwner] = ownerInvitationFixture();

    expect(fn () => $service->validateToken($otherTenant, $otherOwner, $revokedToken))
        ->toThrow(DomainException::class);
});

it('records opened state and consumes an invitation only once', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();
    $service = app(OwnerInvitationService::class);
    ['invitation' => $invitation, 'token' => $token] = $service->issue($tenant, $owner, invitedBy: $admin);

    $opened = $service->markOpened($tenant, $owner, $token);
    $consumed = $service->consumeToken($tenant, $owner, $token, $admin);

    expect($opened->opened_at)->not->toBeNull()
        ->and($consumed->opened_at)->not->toBeNull()
        ->and($consumed->accepted_at)->not->toBeNull()
        ->and($consumed->consumed_at)->not->toBeNull();

    expect(fn () => $service->consumeToken($tenant, $owner, $token, $admin))
        ->toThrow(DomainException::class);
});

it('revokes the previous pending invitation when issuing a replacement', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();
    $service = app(OwnerInvitationService::class);
    ['invitation' => $previous, 'token' => $previousToken] = $service->issue($tenant, $owner, invitedBy: $admin);
    ['invitation' => $replacement, 'token' => $replacementToken] = $service->issue($tenant, $owner, invitedBy: $admin);

    expect($previous->fresh()->isRevoked())->toBeTrue()
        ->and($replacement->resend_count)->toBe(1)
        ->and($service->validateToken($tenant, $owner, $replacementToken)->is($replacement))->toBeTrue();

    expect(fn () => $service->validateToken($tenant, $owner, $previousToken))
        ->toThrow(DomainException::class);

    expect(TenantInvitation::query()->where('tenant_id', $tenant->id)->whereNull('revoked_at')->whereNull('consumed_at')->count())
        ->toBe(1);
});

it('enforces the resend cooldown in the invitation service', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();
    $service = app(OwnerInvitationService::class);
    ['invitation' => $invitation] = $service->issue($tenant, $owner, invitedBy: $admin);

    expect(fn () => $service->resend($tenant, $owner, $admin))
        ->toThrow(DomainException::class);

    $invitation->update(['issued_at' => now()->subSeconds(61)]);
    $replacement = $service->resend($tenant, $owner, $admin);

    expect($replacement['invitation']->resend_count)->toBe(1);
});

it('uses row locking for atomic consumption and leaves no raw token in Activitylog', function (): void {
    ['tenant' => $tenant, 'owner' => $owner, 'admin' => $admin] = ownerInvitationFixture();
    $service = app(OwnerInvitationService::class);
    ['invitation' => $invitation, 'token' => $token] = $service->issue($tenant, $owner, invitedBy: $admin);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $service->consumeToken($tenant, $owner, $token, $admin);

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'tenant_invitations') && str_contains($sql, 'for update')))
        ->toBeTrue();

    $activity = Activity::query()
        ->where('subject_type', TenantInvitation::class)
        ->where('subject_id', $invitation->id)
        ->get();

    expect($activity->pluck('event')->all())->toContain('invitation.issued', 'invitation.accepted', 'invitation.consumed')
        ->and($activity->toJson())->not->toContain($token);
});
