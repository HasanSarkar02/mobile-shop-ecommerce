<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class OwnershipTransferService
{
    public function __construct(private readonly OwnerInvitationService $invitations) {}

    /** @return array{invitation: TenantInvitation, token: string} */
    public function start(Tenant $tenant, User $target, User $actor): array
    {
        if (! $actor->is_platform_admin) {
            throw new DomainException('Only a Platform Admin can start an ownership transfer.');
        }

        return DB::transaction(function () use ($tenant, $target, $actor): array {
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $primaryOwner = User::query()
                ->whereKey($lockedTenant->getAttribute('primary_owner_id'))
                ->where('tenant_id', $lockedTenant->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->first();

            if (! $primaryOwner instanceof User) {
                throw new DomainException('The tenant has no valid primary owner.');
            }

            if (! $lockedTarget instanceof User || $lockedTarget->id === $primaryOwner->id) {
                throw new DomainException('The ownership transfer target is invalid.');
            }

            $issued = $this->invitations->issueTransfer($lockedTenant, $lockedTarget, $actor, $primaryOwner);
            activity('owners')
                ->performedOn($lockedTenant)
                ->causedBy($actor)
                ->event('owner.transfer.issued')
                ->withProperties([
                    'tenant_id' => $lockedTenant->id,
                    'previous_primary_owner_id' => $primaryOwner->id,
                    'target_user_id' => $lockedTarget->id,
                    'invitation_id' => $issued['invitation']->id,
                ])
                ->log('owner.transfer.issued');

            return $issued;
        });
    }
}
