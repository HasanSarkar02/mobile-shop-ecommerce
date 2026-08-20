<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class OwnerLifecycleService
{
    public function __construct(
        private readonly OwnerInvitationService $invitations,
        private readonly SessionRevocationService $sessions,
    ) {}

    public function activate(Tenant $tenant, User $owner, User $actor): User
    {
        $this->assertPlatformActor($actor);

        return DB::transaction(function () use ($tenant, $owner, $actor): User {
            $locked = $this->lockOwner($tenant, $owner);

            if ($locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => true,
                'deactivated_at' => null,
            ])->save();
            $locked = $locked->refresh();
            $this->log($locked, 'owner.activated', $tenant, $actor);

            return $locked;
        });
    }

    public function deactivate(Tenant $tenant, User $owner, User $actor): User
    {
        $this->assertPlatformActor($actor);

        return DB::transaction(function () use ($tenant, $owner, $actor): User {
            $locked = $this->lockOwner($tenant, $owner);

            if (! $locked->is_active) {
                return $locked;
            }

            $activeOwnerCount = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->lockForUpdate()
                ->count();

            if ($activeOwnerCount <= 1) {
                throw new DomainException('The tenant must retain at least one active owner.');
            }

            $locked->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
            ])->save();
            $this->sessions->revoke($locked);
            $locked = $locked->refresh();
            $this->log($locked, 'owner.deactivated', $tenant, $actor);

            return $locked;
        });
    }

    /** @return array{invitation: TenantInvitation, token: string} */
    public function resetPassword(Tenant $tenant, User $owner, User $actor): array
    {
        $this->assertPlatformActor($actor);

        $locked = $this->lockOwner($tenant, $owner);

        if (! $locked->is_active) {
            throw new DomainException('An inactive owner cannot receive a password reset.');
        }

        $this->sessions->revoke($locked);
        $issued = $this->invitations->issue(
            $tenant,
            $locked,
            TenantInvitation::SOURCE_PASSWORD_RESET,
            $actor,
            ttlMinutes: 60,
            cooldownSeconds: 60,
        );
        $this->log($locked, 'owner.password_reset', $tenant, $actor);

        return $issued;
    }

    private function lockOwner(Tenant $tenant, User $owner): User
    {
        $locked = User::query()
            ->whereKey($owner->getKey())
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof User) {
            throw new DomainException('The owner does not belong to this tenant.');
        }

        return $locked;
    }

    private function assertPlatformActor(User $actor): void
    {
        if (! $actor->is_platform_admin) {
            throw new DomainException('Only a Platform Admin can manage owners.');
        }
    }

    private function log(User $owner, string $event, Tenant $tenant, User $actor): void
    {
        activity('owners')
            ->performedOn($owner)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
            ])
            ->log($event);
    }
}
