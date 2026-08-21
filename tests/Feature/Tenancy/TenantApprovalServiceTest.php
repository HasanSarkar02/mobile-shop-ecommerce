<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Notifications\ShopApprovedNotification;
use App\Notifications\ShopRejectedNotification;
use App\Services\TenantApprovalService;
use App\Services\TenantBootstrapService;

beforeEach(function (): void {
    seedBootstrapPlans();
});

it('bootstraps a pending tenant without a subscription', function (): void {
    [$tenant] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Pending Shop',
        'subdomain' => 'pendingshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'pending-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    expect($tenant->status)->toBe('pending')
        ->and($tenant->plan)->toBe('trial')
        ->and($tenant->subscription)->toBeNull()
        ->and($tenant->isActive())->toBeFalse();
});

it('approves a pending tenant and starts a fresh trial subscription', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Approve Shop',
        'subdomain' => 'approveshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'approve-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    app(TenantApprovalService::class)->approve($tenant);

    $tenant->refresh();

    expect($tenant->status)->toBe('trial')
        ->and($tenant->isActive())->toBeTrue();

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->slug)->toBe('trial')
        ->and($subscription->current_period_ends_at->isFuture())->toBeTrue();

    Notification::assertSentTo($owner, ShopApprovedNotification::class);
});

it('rejects a pending tenant, frees the subdomain and notifies the owner', function (): void {
    Notification::fake();

    [$tenant, $owner] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Reject Shop',
        'subdomain' => 'rejectshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'reject-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    $originalSubdomain = $tenant->subdomain;

    app(TenantApprovalService::class)->reject($tenant, null, 'Duplicate shop.');

    $tenant->refresh();

    expect($tenant->status)->toBe('rejected')
        ->and($tenant->subdomain)->not->toBe($originalSubdomain)
        ->and(Tenant::query()->where('subdomain', $originalSubdomain)->exists())->toBeFalse();

    Notification::assertSentTo($owner, ShopRejectedNotification::class);
});

it('throws when approving or rejecting a non-pending tenant', function (): void {
    [$tenant] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Trial Shop',
        'subdomain' => 'trialshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'trial-owner@example.com', 'password' => 'secret1234'],
    ]);

    expect(fn () => app(TenantApprovalService::class)->approve($tenant))
        ->toThrow(DomainException::class);

    expect(fn () => app(TenantApprovalService::class)->reject($tenant))
        ->toThrow(DomainException::class);
});

it('releases pending tenants whose approval window has expired', function (): void {
    Notification::fake();

    [$tenant] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Expired Shop',
        'subdomain' => 'expiredshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'expired-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    Tenant::query()->whereKey($tenant->id)->update(['created_at' => now()->subDays(10)]);

    $count = app(TenantApprovalService::class)->releaseExpiredPending(7);

    expect($count)->toBe(1);

    $tenant->refresh();

    expect($tenant->status)->toBe('rejected')
        ->and(Tenant::query()->where('subdomain', 'expiredshop')->exists())->toBeFalse();
});

it('leaves fresh pending tenants untouched when releasing expired ones', function (): void {
    [$fresh] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Fresh Shop',
        'subdomain' => 'freshshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'fresh-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    $count = app(TenantApprovalService::class)->releaseExpiredPending(7);

    expect($count)->toBe(0)
        ->and($fresh->fresh()->status)->toBe('pending');
});
