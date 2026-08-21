<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\ShopPendingApprovalNotification;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Notifications\WelcomeTenantOwnerNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Single authoritative tenant provisioning path shared by the public signup
 * and the Platform Admin "Create Tenant" flow. All writes happen inside one
 * transaction; the TenantObserver still owns the store content scaffolding
 * (location, theme, settings, notification templates).
 */
final class TenantBootstrapService
{
    public const OWNER_MODE_EXPLICIT = 'explicit';

    public const OWNER_MODE_INVITATION = 'invitation';

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly OwnerInvitationService $invitations,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     subdomain: string,
     *     plan: string,
     *     currency?: string,
     *     contact_email?: string|null,
     *     contact_phone?: string|null,
     *     owner: array{name: string, email: string, password?: string|null, phone?: string|null},
     * }  $data
     * @param  string|null  $initialStatus  Optional tenant status override. When
     *                                      'pending' the tenant is created without a
     *                                      subscription and the owner is told their
     *                                      shop is under review (public signup approval
     *                                      gate). Null keeps the plan-derived default.
     * @return array{0: Tenant, 1: User}
     */
    public function bootstrap(array $data, string $ownerMode = self::OWNER_MODE_EXPLICIT, ?User $invitedBy = null, ?string $initialStatus = null): array
    {
        return DB::transaction(function () use ($data, $ownerMode, $invitedBy, $initialStatus): array {
            $plan = $this->resolveActivePlan($data['plan']);
            $isTrial = $plan->slug === 'trial';

            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'subdomain' => strtolower($data['subdomain']),
                'status' => $initialStatus ?? ($isTrial ? 'trial' : 'active'),
                'plan' => $plan->slug,
                'currency' => $data['currency'] ?? 'BDT',
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
            ]);

            if ($initialStatus !== 'pending') {
                $this->createSubscription($tenant, $plan, $isTrial);
            }

            $owner = $this->createOwner($tenant, $data['owner'], $ownerMode);
            $tenant->forceFill(['primary_owner_id' => $owner->id])->save();

            $this->notifyOwner($tenant, $owner, $ownerMode, $invitedBy, $initialStatus);

            return [$tenant, $owner];
        });
    }

    private function resolveActivePlan(string $slug): Plan
    {
        $plan = Plan::query()->where('slug', $slug)->where('is_active', true)->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan' => 'The selected plan is not available.',
            ]);
        }

        return $plan;
    }

    private function createSubscription(Tenant $tenant, Plan $plan, bool $isTrial): void
    {
        if ($isTrial) {
            $this->subscriptions->startTrial($tenant, $plan, (int) config('tenancy.trial_days'));

            return;
        }

        $this->subscriptions->activatePlan($tenant, $plan);
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, phone?: string|null}  $ownerData
     */
    private function createOwner(Tenant $tenant, array $ownerData, string $ownerMode): User
    {
        $email = strtolower(trim($ownerData['email']));
        $name = trim($ownerData['name']);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'A user with this email already exists.',
            ]);
        }

        if ($ownerMode === self::OWNER_MODE_INVITATION) {
            $password = bin2hex(random_bytes(32));
        } elseif (! is_string($ownerData['password'] ?? null)) {
            throw ValidationException::withMessages([
                'owner_email' => 'A password is required for explicit owner onboarding.',
            ]);
        }

        $password ??= (string) $ownerData['password'];

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'phone' => $ownerData['phone'] ?? null,
            'password' => Hash::make($password),
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true])->save();

        return $owner;
    }

    private function notifyOwner(Tenant $tenant, User $owner, string $ownerMode, ?User $invitedBy, ?string $initialStatus): void
    {
        if ($initialStatus === 'pending') {
            $owner->notify(new ShopPendingApprovalNotification($tenant));

            return;
        }

        if ($ownerMode === self::OWNER_MODE_INVITATION) {
            $issued = $this->invitations->issue(
                $tenant,
                $owner,
                TenantInvitation::SOURCE_PLATFORM,
                $invitedBy,
            );
            $expiresAt = $issued['invitation']->getAttribute('expires_at');

            if (! $expiresAt instanceof CarbonInterface) {
                throw new LogicException('The invitation expiry is invalid.');
            }

            $owner->notify(new TenantOwnerInvitationNotification(
                $tenant,
                $issued['token'],
                $expiresAt,
            ));

            return;
        }

        $owner->notify(new WelcomeTenantOwnerNotification($tenant));
    }
}
