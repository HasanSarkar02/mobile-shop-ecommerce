<?php

declare(strict_types=1);

use App\Livewire\TenantSignupForm;
use App\Models\Location;
use App\Models\NotificationTemplate;
use App\Models\StoreSetting;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ShopNeedsApprovalNotification;
use App\Notifications\ShopPendingApprovalNotification;
use App\Notifications\WelcomeTenantOwnerNotification;
use App\Services\TenantBootstrapService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    seedBootstrapPlans();
});

it('public signup creates a pending tenant with no subscription yet', function (): void {
    Notification::fake();

    $admins = User::factory()->count(2)->create(['is_platform_admin' => true]);

    Livewire::test(TenantSignupForm::class)
        ->set('business_name', 'Public Shop')
        ->set('subdomain', 'publicshop')
        ->set('owner_name', 'Karim Rahim')
        ->set('owner_email', 'public@example.com')
        ->set('owner_phone', '01712345678')
        ->set('password', 'secret1234')
        ->set('password_confirmation', 'secret1234')
        ->call('register')
        ->assertRedirect(route('platform.signup.pending'));

    $tenant = Tenant::query()->where('subdomain', 'publicshop')->firstOrFail();

    expect($tenant->status)->toBe('pending')
        ->and($tenant->plan)->toBe('trial')
        ->and($tenant->subscription)->toBeNull();

    $owner = User::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->firstOrFail();
    expect($owner->email)->toBe('public@example.com')
        ->and($owner->phone)->toBe('01712345678')
        ->and($owner->is_platform_admin)->not->toBeTrue();

    app(Tenancy::class)->set($tenant);

    expect(Location::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreThemeSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(StoreSetting::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(NotificationTemplate::query()->where('tenant_id', $tenant->id)->count())->toBe(11);

    Notification::assertSentTo($owner, ShopPendingApprovalNotification::class);
    Notification::assertNotSentTo($owner, WelcomeTenantOwnerNotification::class);

    foreach ($admins as $admin) {
        Notification::assertSentTo($admin, ShopNeedsApprovalNotification::class);
    }
});

it('rejects an invalid Bangladeshi mobile number on signup', function (): void {
    Notification::fake();

    Livewire::test(TenantSignupForm::class)
        ->set('business_name', 'Bad Phone Shop')
        ->set('subdomain', 'badphoneshop')
        ->set('owner_name', 'Karim Rahim')
        ->set('owner_email', 'badphone@example.com')
        ->set('owner_phone', '12345')
        ->set('password', 'secret1234')
        ->set('password_confirmation', 'secret1234')
        ->call('register')
        ->assertHasErrors('owner_phone');

    expect(Tenant::query()->where('subdomain', 'badphoneshop')->exists())->toBeFalse();
});

it('public signup keeps the pending tenant locked until approval', function (): void {
    [$tenant] = app(TenantBootstrapService::class)->bootstrap([
        'name' => 'Locked Shop',
        'subdomain' => 'lockedshop',
        'plan' => 'trial',
        'owner' => ['name' => 'Rahim', 'email' => 'locked-owner@example.com', 'password' => 'secret1234'],
    ], initialStatus: 'pending');

    expect($tenant->isActive())->toBeFalse();

    $this->get('http://lockedshop.'.config('tenancy.central_domain'))
        ->assertNotFound();
});
