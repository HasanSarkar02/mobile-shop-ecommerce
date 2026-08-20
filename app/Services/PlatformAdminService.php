<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeploymentMode;
use App\Models\PlatformAdminInvitation;
use App\Models\User;
use App\Notifications\PlatformAdminInvitationNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PlatformAdminService
{
    public function __construct(private readonly SessionRevocationService $sessions) {}

    /** @return array{user: User, invitation: PlatformAdminInvitation, token: string} */
    public function invite(string $name, string $email, User $actor): array
    {
        $this->assertActor($actor);
        $email = strtolower(trim($email));

        return DB::transaction(function () use ($name, $email, $actor): array {
            $existing = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->tenant_id !== null || $existing->role !== null || $existing->is_platform_admin) {
                    throw new DomainException('A user with this email already exists and is not eligible for a Platform Admin invitation.');
                }

                $user = $existing;
                $user->forceFill(['name' => trim($name), 'is_platform_admin' => true, 'is_active' => false])->save();
            } else {
                $user = new User;
                $user->forceFill([
                    'name' => trim($name),
                    'email' => $email,
                    'password' => Hash::make(bin2hex(random_bytes(32))),
                    'tenant_id' => null,
                    'role' => null,
                    'is_platform_admin' => true,
                    'is_active' => false,
                ])->save();
            }

            $issued = $this->issueInvitation($user, $actor);
            $user->notify(new PlatformAdminInvitationNotification($issued['token'], $issued['invitation']->expires_at));
            $this->log($user, 'platform_admin.invited', $actor);

            return ['user' => $user, ...$issued];
        });
    }

    public function grant(User $target, User $actor): User
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($target, $actor): User {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_platform_admin) {
                throw new DomainException('This user is already a Platform Admin.');
            }

            if ($locked->tenant_id !== null || $locked->role !== null) {
                throw new DomainException('Only an unassigned user without a tenant role can become a Platform Admin.');
            }

            $locked->forceFill(['is_platform_admin' => true, 'is_active' => true])->save();
            $locked = $locked->refresh();
            $this->log($locked, 'platform_admin.granted', $actor);

            return $locked;
        });
    }

    public function activate(User $admin, User $actor): User
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($admin, $actor): User {
            $locked = $this->lockAdmin($admin);

            if ($locked->password_changed_at === null) {
                throw new DomainException('This Platform Admin has not completed their setup yet.');
            }

            $locked->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
            $locked = $locked->refresh();
            $this->log($locked, 'platform_admin.activated', $actor);

            return $locked;
        });
    }

    public function deactivate(User $admin, User $actor): User
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($admin, $actor): User {
            $locked = $this->lockAdmin($admin);
            $this->assertNotLastActiveAdmin($locked);
            $locked->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
            $this->sessions->revoke($locked);
            $locked = $locked->refresh();
            $this->log($locked, 'platform_admin.deactivated', $actor);

            return $locked;
        });
    }

    public function revoke(User $admin, User $actor): User
    {
        $this->assertActor($actor);

        return DB::transaction(function () use ($admin, $actor): User {
            $locked = $this->lockAdmin($admin);
            $this->assertNotLastActiveAdmin($locked);
            $locked->forceFill(['is_platform_admin' => false, 'is_active' => false, 'deactivated_at' => now()])->save();
            $this->sessions->revoke($locked);
            $locked = $locked->refresh();
            $this->log($locked, 'platform_admin.revoked', $actor);

            return $locked;
        });
    }

    /** @return array{invitation: PlatformAdminInvitation, token: string} */
    public function resetPassword(User $admin, User $actor): array
    {
        $this->assertActor($actor);
        $locked = $this->lockAdmin($admin);
        $this->sessions->revoke($locked);
        $issued = $this->issueInvitation($locked, $actor);
        $locked->notify(new PlatformAdminInvitationNotification($issued['token'], $issued['invitation']->expires_at));
        $this->log($locked, 'platform_admin.password_reset', $actor);

        return $issued;
    }

    public function resetMfa(User $admin, User $actor): User
    {
        $this->assertActor($actor);
        $locked = $this->lockAdmin($admin);
        $locked->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();
        $this->sessions->revoke($locked);
        $locked = $locked->refresh();
        $this->log($locked, 'platform_admin.mfa_reset', $actor);

        return $locked;
    }

    public function acceptInvitation(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $digest = hash('sha256', $token);
            $invitation = PlatformAdminInvitation::query()
                ->whereNull('revoked_at')
                ->whereNull('accepted_at')
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get()
                ->first(fn (PlatformAdminInvitation $candidate): bool => hash_equals((string) $candidate->token_digest, $digest));

            if ($invitation === null || $invitation->isExpired()) {
                throw new DomainException('The Platform Admin invitation is invalid or expired.');
            }

            $user = User::query()->whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            if (! $user->is_platform_admin) {
                throw new DomainException('The Platform Admin invitation is no longer valid.');
            }

            $now = now();
            $user->forceFill([
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => $now,
                'password_changed_at' => $now,
            ])->save();
            $invitation->update(['accepted_at' => $now, 'consumed_at' => $now]);
            $this->log($user, 'platform_admin.invitation_accepted', $user);

            return $user->refresh();
        });
    }

    private function issueInvitation(User $user, User $actor): array
    {
        $token = bin2hex(random_bytes(32));
        $now = now();
        $previous = PlatformAdminInvitation::query()
            ->where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->get();
        foreach ($previous as $invitation) {
            $invitation->update(['revoked_at' => $now]);
        }

        $invitation = PlatformAdminInvitation::query()->create([
            'user_id' => $user->id,
            'invited_by' => $actor->id,
            'token_digest' => hash('sha256', $token),
            'issued_at' => $now,
            'expires_at' => $now->copy()->addMinutes(60),
            'resend_count' => $previous->isNotEmpty() ? 1 : 0,
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    private function lockAdmin(User $admin): User
    {
        $locked = User::query()->whereKey($admin->getKey())->where('is_platform_admin', true)->lockForUpdate()->first();
        if (! $locked instanceof User) {
            throw new DomainException('The target is not a Platform Admin.');
        }

        return $locked;
    }

    private function assertNotLastActiveAdmin(User $admin): void
    {
        $count = User::query()->where('is_platform_admin', true)->where('is_active', true)->lockForUpdate()->count();
        if ($admin->is_active && $count <= 1) {
            throw new DomainException('The platform must retain at least one active Platform Admin.');
        }
    }

    private function assertActor(User $actor): void
    {
        if (config('deployment.mode') !== DeploymentMode::SaaS->value || ! $actor->is_platform_admin || ! $actor->is_active) {
            throw new DomainException('Only an active Platform Admin can manage Platform Admins.');
        }
    }

    private function log(User $user, string $event, User $actor): void
    {
        activity('platform-admins')
            ->performedOn($user)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['user_id' => $user->id])
            ->log($event);
    }
}
