<?php

declare(strict_types=1);

use App\Filament\Store\Resources\StaffResource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Auth;

it('limits the staff query and record authorization to the current tenant', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'staff-scope-a']);
    $tenantB = Tenant::factory()->create(['subdomain' => 'staff-scope-b']);
    $staffA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'staff']);
    $staffB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'staff']);
    $owner = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);

    app(Tenancy::class)->set($tenantA);
    Auth::login($owner);

    expect(StaffResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$staffA->id])
        ->and(StaffResource::canView($staffA))->toBeTrue()
        ->and(StaffResource::canEdit($staffA))->toBeTrue()
        ->and(StaffResource::canDelete($staffA))->toBeTrue()
        ->and(StaffResource::canView($staffB))->toBeFalse()
        ->and(StaffResource::canEdit($staffB))->toBeFalse()
        ->and(StaffResource::canDelete($staffB))->toBeFalse();

    Auth::logout();
});
