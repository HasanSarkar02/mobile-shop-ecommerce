<?php

declare(strict_types=1);

use App\Filament\Store\Resources\CouponResource;
use App\Filament\Store\Resources\CustomerResource;
use App\Filament\Store\Resources\LocationResource;
use App\Filament\Store\Resources\PaymentMethodResource;
use App\Filament\Store\Resources\StaffResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function makeStaffUser(Tenant $tenant, string $role): User
{
    return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
}

it('restricts fully owner-locked resources to owners only', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = makeStaffUser($tenant, 'owner');
    $staff = makeStaffUser($tenant, 'staff');

    Auth::login($owner);
    expect(StaffResource::canViewAny())->toBeTrue();
    expect(PaymentMethodResource::canViewAny())->toBeTrue();
    expect(LocationResource::canViewAny())->toBeTrue();

    Auth::login($staff);
    expect(StaffResource::canViewAny())->toBeFalse();
    expect(PaymentMethodResource::canViewAny())->toBeFalse();
    expect(LocationResource::canViewAny())->toBeFalse();

    Auth::logout();
});

it('restricts coupon mutations to owners while leaving viewing available to staff', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = makeStaffUser($tenant, 'owner');
    $staff = makeStaffUser($tenant, 'staff');

    Auth::login($owner);
    expect(CouponResource::canCreate())->toBeTrue();

    Auth::login($staff);
    expect(CouponResource::canCreate())->toBeFalse();
    // Filament's default (unrestricted) canViewAny still applies — staff can
    // see active coupons to help customers, only mutating is owner-only.
    expect(CouponResource::canViewAny())->toBeTrue();

    Auth::logout();
});

it('restricts customer deletion to owners while leaving edit/view available to staff', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = makeStaffUser($tenant, 'owner');
    $staff = makeStaffUser($tenant, 'staff');

    Auth::login($owner);
    expect(CustomerResource::canDeleteAny())->toBeTrue();

    Auth::login($staff);
    expect(CustomerResource::canDeleteAny())->toBeFalse();
    expect(CustomerResource::canViewAny())->toBeTrue();

    Auth::logout();
});
