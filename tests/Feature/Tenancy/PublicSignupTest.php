<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Livewire\TenantSignupForm;
use App\Models\Location;
use App\Models\NotificationTemplate;
use App\Models\StoreSetting;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeTenantOwnerNotification;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    seedBootstrapPlans();
});

it('public signup still produces a fully bootstrapped tenant', function (): void {
    Notification::fake();

    Livewire::test(TenantSignupForm::class)
        ->set('business_name', 'Public Shop')
        ->set('subdomain', 'publicshop')
        ->set('owner_name', 'Karim Rahim')
        ->set('owner_email', 'public@example.com')
        ->set('password', 'secret1234')
        ->set('password_confirmation', 'secret1234')
        ->call('register');

    $tenant = Tenant::query()->where('subdomain', 'publicshop')->firstOrFail();

    expect($tenant->status)->toBe('trial');

    $owner = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->firstOrFail();
    expect($owner->email)->toBe('public@example.com')
        ->and($owner->is_platform_admin)->not->toBeTrue();

    $subscription = $tenant->subscription;
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->slug)->toBe('trial');

    app(Tenancy::class)->set($tenant);

    expect(Location::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreThemeSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(NotificationTemplate::query()->where('tenant_id', $tenant->id)->count())->toBe(11);

    Notification::assertSentTo($owner, WelcomeTenantOwnerNotification::class);
});
