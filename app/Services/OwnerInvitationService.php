<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class OwnerInvitationService
{
    /** @return array{invitation: TenantInvitation, token: string} */
    public function issue(
        Tenant $tenant,
        User $user,
        string $source = TenantInvitation::SOURCE_PLATFORM,
        ?User $invitedBy = null,
        int $ttlMinutes = 60,
        ?int $cooldownSeconds = null,
        string $purpose = TenantInvitation::PURPOSE_OWNER_ONBOARDING,
        ?int $previousPrimaryOwnerId = null,
        bool $ownerOnly = true,
    ): array {
        $ownerOnly ? $this->assertTarget($tenant, $user) : $this->assertTransferTarget($tenant, $user);

        if ($invitedBy !== null && ! $invitedBy->is_platform_admin) {
            throw new DomainException('Only a Platform Admin can issue a platform invitation.');
        }

        $token = bin2hex(random_bytes(32));
        $digest = hash('sha256', $token);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(max(1, $ttlMinutes));

        return DB::transaction(function () use ($tenant, $user, $source, $invitedBy, $token, $digest, $issuedAt, $expiresAt, $cooldownSeconds, $purpose, $previousPrimaryOwnerId, $ownerOnly): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $ownerOnly ? $this->assertTarget($tenant, $lockedUser) : $this->assertTransferTarget($tenant, $lockedUser);

            $previous = TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->get();

            if ($cooldownSeconds !== null) {
                $latest = $previous->sortByDesc('id')->first();
                $issuedAtValue = $latest?->getAttribute('issued_at');

                if ($issuedAtValue instanceof CarbonInterface
                    && $issuedAtValue->isAfter(now()->subSeconds(max(1, $cooldownSeconds)))) {
                    throw new DomainException('Please wait before requesting another invitation.');
                }
            }

            $resendCount = 0;

            foreach ($previous as $invitation) {
                $resendCount = max($resendCount, (int) $invitation->resend_count);

                if (! $invitation->isConsumed() && ! $invitation->isRevoked()) {
                    $invitation->update([
                        'revoked_at' => $issuedAt,
                        'delivery_status' => TenantInvitation::DELIVERY_REVOKED,
                    ]);
                    $this->log($invitation, 'invitation.revoked', $invitedBy, $tenant, $lockedUser);
                }
            }

            if ($previous->isNotEmpty()) {
                $resendCount++;
            }

            $invitation = TenantInvitation::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $lockedUser->id,
                'invited_by' => $invitedBy?->id,
                'source' => $source,
                'purpose' => $purpose,
                'previous_primary_owner_id' => $previousPrimaryOwnerId,
                'token_digest' => $digest,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'resend_count' => $resendCount,
                'delivery_status' => TenantInvitation::DELIVERY_QUEUED,
            ]);

            $this->log($invitation, 'invitation.issued', $invitedBy, $tenant, $lockedUser);

            return ['invitation' => $invitation, 'token' => $token];
        });
    }

    public function latestFor(Tenant $tenant, User $user): ?TenantInvitation
    {
        $this->assertTarget($tenant, $user);

        return TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereNull('accepted_at')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    /** @return array{invitation: TenantInvitation, token: string} */
    public function resend(
        Tenant $tenant,
        User $user,
        ?User $invitedBy = null,
        int $ttlMinutes = 60,
        int $cooldownSeconds = 60,
    ): array {
        return $this->issue(
            $tenant,
            $user,
            TenantInvitation::SOURCE_PLATFORM,
            $invitedBy,
            $ttlMinutes,
            $cooldownSeconds,
        );
    }

    /** @return array{invitation: TenantInvitation, token: string} */
    public function issueTransfer(Tenant $tenant, User $target, User $actor, User $previousPrimaryOwner): array
    {
        if (! $actor->is_platform_admin) {
            throw new DomainException('Only a Platform Admin can start an ownership transfer.');
        }

        if ((int) $tenant->getAttribute('primary_owner_id') !== (int) $previousPrimaryOwner->id) {
            throw new DomainException('The previous primary owner is no longer current.');
        }

        return $this->issue(
            $tenant,
            $target,
            TenantInvitation::SOURCE_PLATFORM,
            $actor,
            ttlMinutes: 60,
            cooldownSeconds: 60,
            purpose: TenantInvitation::PURPOSE_OWNER_TRANSFER,
            previousPrimaryOwnerId: $previousPrimaryOwner->id,
            ownerOnly: false,
        );
    }

    public function validateToken(Tenant $tenant, User $user, string $token): TenantInvitation
    {
        $this->assertTarget($tenant, $user);

        if ($token === '') {
            throw new DomainException('The invitation token is invalid.');
        }

        $digest = hash('sha256', $token);
        $invitation = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

        if ($invitation === null) {
            throw new DomainException('The invitation token is invalid.');
        }

        $this->assertUsable($invitation);

        return $invitation;
    }

    public function validateTokenForTenant(Tenant $tenant, string $token): TenantInvitation
    {
        if (! $tenant->isActive() || $token === '') {
            throw new DomainException('The invitation token is invalid.');
        }

        $digest = hash('sha256', $token);
        $invitation = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->get()
            ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

        if ($invitation === null) {
            throw new DomainException('The invitation token is invalid.');
        }

        $user = User::query()->find($invitation->user_id);

        if (! $user instanceof User) {
            throw new DomainException('The invitation target is invalid.');
        }

        $this->assertInvitationTarget($tenant, $user, $invitation);
        $this->assertUsable($invitation);
        $invitation->setRelation('user', $user);

        return $invitation;
    }

    public function markOpened(Tenant $tenant, User $user, string $token): TenantInvitation
    {
        return DB::transaction(function () use ($tenant, $user, $token): TenantInvitation {
            $lockedInvitations = TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();
            $digest = hash('sha256', $token);
            $locked = $lockedInvitations
                ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

            if ($locked === null) {
                throw new DomainException('The invitation token is invalid.');
            }

            $this->assertInvitationTarget($tenant, $user, $locked);
            $this->assertUsable($locked);

            if ($locked->opened_at === null) {
                $locked->update(['opened_at' => now()]);
            }

            return $locked->refresh();
        });
    }

    public function consumeToken(Tenant $tenant, User $user, string $token, ?User $actor = null): TenantInvitation
    {
        $this->assertTarget($tenant, $user);

        if (! $user->is_active) {
            throw new DomainException('The invitation target is inactive.');
        }

        return DB::transaction(function () use ($tenant, $user, $token, $actor): TenantInvitation {
            $lockedInvitations = TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();
            $digest = hash('sha256', $token);
            $invitation = $lockedInvitations
                ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

            if ($invitation === null) {
                throw new DomainException('The invitation token is invalid.');
            }

            $this->assertUsable($invitation);
            $now = now();
            $invitation->update([
                'opened_at' => $invitation->opened_at ?? $now,
                'accepted_at' => $now,
                'consumed_at' => $now,
            ]);
            $invitation = $invitation->refresh();
            $this->log($invitation, 'invitation.accepted', $actor, $tenant, $user);
            $this->log($invitation, 'invitation.consumed', $actor, $tenant, $user);

            return $invitation;
        });
    }

    public function acceptToken(Tenant $tenant, User $user, string $token, string $password): User
    {
        $this->assertTarget($tenant, $user);

        if (! $user->is_active) {
            throw new DomainException('The invitation target is inactive.');
        }

        if (! $tenant->isActive()) {
            throw new DomainException('This tenant is not active.');
        }

        return DB::transaction(function () use ($tenant, $user, $token, $password): User {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $this->assertTarget($tenant, $lockedUser);
            $lockedInvitations = TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->get();
            $digest = hash('sha256', $token);
            $invitation = $lockedInvitations
                ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

            if ($invitation === null) {
                throw new DomainException('The invitation token is invalid.');
            }

            $this->assertUsable($invitation);
            $now = now();
            $lockedUser->forceFill(['password' => Hash::make($password)])->save();
            $invitation->update([
                'opened_at' => $invitation->opened_at ?? $now,
                'accepted_at' => $now,
                'consumed_at' => $now,
            ]);
            $invitation = $invitation->refresh();
            $this->log($invitation, 'invitation.accepted', $lockedUser, $tenant, $lockedUser);
            $this->log($invitation, 'invitation.consumed', $lockedUser, $tenant, $lockedUser);

            return $lockedUser->refresh();
        });
    }

    public function acceptTransferToken(Tenant $tenant, string $token, string $password): User
    {
        if (! $tenant->isActive() || $token === '') {
            throw new DomainException('The transfer invitation is invalid.');
        }

        return DB::transaction(function () use ($tenant, $token, $password): User {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvitations = TenantInvitation::query()
                ->where('tenant_id', $lockedTenant->id)
                ->lockForUpdate()
                ->get();
            $digest = hash('sha256', $token);
            $invitation = $lockedInvitations
                ->first(fn (TenantInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

            if ($invitation === null || $invitation->purpose !== TenantInvitation::PURPOSE_OWNER_TRANSFER) {
                throw new DomainException('The transfer invitation is invalid.');
            }

            $target = User::query()->whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            $this->assertTransferTarget($lockedTenant, $target);
            $this->assertUsable($invitation);

            if ((int) $lockedTenant->getAttribute('primary_owner_id') !== (int) $invitation->previous_primary_owner_id) {
                throw new DomainException('The transfer invitation is stale.');
            }

            $previousOwner = User::query()
                ->whereKey($lockedTenant->getAttribute('primary_owner_id'))
                ->where('tenant_id', $lockedTenant->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $previousOwner instanceof User || $previousOwner->id === $target->id) {
                throw new DomainException('The current primary owner is invalid.');
            }

            $now = now();
            $target->forceFill([
                'role' => 'owner',
                'password' => Hash::make($password),
                'password_changed_at' => $now,
            ])->save();
            $lockedTenant->forceFill(['primary_owner_id' => $target->id])->save();
            $invitation->update([
                'opened_at' => $invitation->opened_at ?? $now,
                'accepted_at' => $now,
                'consumed_at' => $now,
            ]);
            $invitation = $invitation->refresh();
            $this->log($invitation, 'invitation.accepted', $target, $lockedTenant, $target);
            $this->log($invitation, 'invitation.consumed', $target, $lockedTenant, $target);
            $this->logOwnerTransfer($lockedTenant, $previousOwner, $target, $invitation);

            return $target->refresh();
        });
    }

    public function revoke(TenantInvitation $invitation, ?User $actor = null): TenantInvitation
    {
        return DB::transaction(function () use ($invitation, $actor): TenantInvitation {
            $locked = TenantInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isConsumed()) {
                throw new DomainException('A consumed invitation cannot be revoked.');
            }

            if ($locked->isRevoked()) {
                return $locked;
            }

            $locked->update([
                'revoked_at' => now(),
                'delivery_status' => TenantInvitation::DELIVERY_REVOKED,
            ]);
            $locked = $locked->refresh();
            $tenant = Tenant::query()->findOrFail((int) $locked->tenant_id);
            $user = User::query()->findOrFail((int) $locked->user_id);
            $this->log($locked, 'invitation.revoked', $actor, $tenant, $user);

            return $locked;
        });
    }

    private function assertTarget(Tenant $tenant, User $user): void
    {
        if ((int) $user->tenant_id !== (int) $tenant->id || $user->role !== 'owner') {
            throw new DomainException('The invitation target is not an owner of this tenant.');
        }
    }

    private function assertTransferTarget(Tenant $tenant, User $user): void
    {
        if ((int) $user->tenant_id !== (int) $tenant->id || ! in_array($user->role, ['owner', 'staff'], true) || ! $user->is_active) {
            throw new DomainException('The transfer target is not an active user of this tenant.');
        }
    }

    private function assertInvitationTarget(Tenant $tenant, User $user, TenantInvitation $invitation): void
    {
        if ($invitation->purpose === TenantInvitation::PURPOSE_OWNER_TRANSFER) {
            $this->assertTransferTarget($tenant, $user);

            return;
        }

        $this->assertTarget($tenant, $user);

        if (! $user->is_active) {
            throw new DomainException('The invitation target is inactive.');
        }
    }

    private function logOwnerTransfer(Tenant $tenant, User $previousOwner, User $target, TenantInvitation $invitation): void
    {
        activity('owners')
            ->performedOn($tenant)
            ->causedBy($target)
            ->event('owner.primary_changed')
            ->withProperties([
                'tenant_id' => $tenant->id,
                'previous_primary_owner_id' => $previousOwner->id,
                'new_primary_owner_id' => $target->id,
                'invitation_id' => $invitation->id,
            ])
            ->log('owner.primary_changed');
    }

    private function assertUsable(TenantInvitation $invitation): void
    {
        if ($invitation->isRevoked()) {
            throw new DomainException('The invitation has been revoked.');
        }

        if ($invitation->isConsumed() || $invitation->accepted_at !== null) {
            throw new DomainException('The invitation has already been consumed.');
        }

        if ($invitation->isExpired()) {
            throw new DomainException('The invitation has expired.');
        }
    }

    private function log(
        TenantInvitation $invitation,
        string $event,
        ?User $actor,
        Tenant $tenant,
        User $user,
    ): void {
        $activity = activity('tenant-invitations')
            ->performedOn($invitation)
            ->event($event)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'source' => $invitation->source,
            ]);

        if ($actor !== null) {
            $activity->causedBy($actor);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log($event);
    }
}
