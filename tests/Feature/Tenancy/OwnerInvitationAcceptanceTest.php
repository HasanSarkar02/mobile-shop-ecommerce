<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\TenantOwnerInvitationNotification;
use App\Services\OwnerInvitationService;
use App\Services\TenantBootstrapService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function acceptedInvitationFixture(): array
{
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Acceptance Shop',
        'subdomain' => 'acceptance-shop',
        'plan' => 'starter',
        'owner' => ['name' => 'Acceptance Owner', 'email' => 'acceptance-owner@example.com'],
    ], ownerMode: TenantBootstrapService::OWNER_MODE_INVITATION);

    $invitation = TenantInvitation::query()->where('tenant_id', $tenant->id)->firstOrFail();
    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class);

    $notification = null;
    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class, function (TenantOwnerInvitationNotification $sent) use (&$notification): bool {
        $notification = $sent;

        return true;
    });

    return [$tenant, $owner, $invitation, $notification->setupToken];
}

beforeEach(function (): void {
    config()->set('deployment.mode', 'saas');
    seedBootstrapPlans();
});

it('accepts a Platform invitation once, creates the password, and regenerates the session', function (): void {
    [$tenant, $owner, $invitation, $token] = acceptedInvitationFixture();
    $host = $tenant->subdomain.'.'.config('tenancy.central_domain');
    $url = 'http://'.$host.'/owner-invitation/'.$token;

    $this->get($url)->assertOk()->assertSee('Set up your admin password');
    $sessionBefore = session()->getId();

    $response = $this->post($url, [
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ]);

    $response->assertRedirect('/admin');
    expect(session()->getId())->not->toBe($sessionBefore)
        ->and(Auth::guard('web')->id())->toBe($owner->id)
        ->and(Hash::check('new-secret-123', $owner->fresh()->password))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and($invitation->fresh()->consumed_at)->not->toBeNull();

    $this->get($url)->assertStatus(410);
});

it('rejects a Platform invitation on another tenant host', function (): void {
    [$tenant, $owner, $invitation, $token] = acceptedInvitationFixture();
    $otherTenant = Tenant::factory()->create([
        'subdomain' => 'other-acceptance-shop',
        'status' => 'active',
    ]);
    $otherPlan = Plan::query()->where('slug', 'starter')->firstOrFail();
    TenantSubscription::query()->create([
        'tenant_id' => $otherTenant->id,
        'plan_id' => $otherPlan->id,
        'status' => 'active',
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $response = $this->get('http://'.$otherTenant->subdomain.'.'.config('tenancy.central_domain').'/owner-invitation/'.$token);

    $response->assertStatus(410);
    expect($invitation->fresh()->consumed_at)->toBeNull()
        ->and($owner->fresh()->tenant_id)->toBe($tenant->id);
});

it('does not include a password in the Platform invitation notification', function (): void {
    [$tenant, $owner] = acceptedInvitationFixture();

    Notification::assertSentTo($owner, TenantOwnerInvitationNotification::class, function (TenantOwnerInvitationNotification $notification) use ($owner): bool {
        expect(property_exists($notification, 'temporaryPassword'))->toBeFalse()
            ->and($notification->setupToken)->toBeString()
            ->and($notification->toMail($owner))->toBeInstanceOf(MailMessage::class);

        return true;
    });
});

it('rejects a non-owner invitation target', function (): void {
    [$tenant, $owner] = acceptedInvitationFixture();
    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'staff',
    ]);

    expect(fn () => app(OwnerInvitationService::class)->issue($tenant, $staff))
        ->toThrow(DomainException::class);
});
