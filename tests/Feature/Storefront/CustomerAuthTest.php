<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

function authBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

it('registers a new customer and logs them in', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);

    $response = $this->post($base.'/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '01712345678',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('storefront.account.dashboard'));
    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
    $customer = Customer::query()->where('tenant_id', $tenant->id)->where('email', 'test@example.com')->firstOrFail();
    expect(Hash::check('password123', $customer->password))->toBeTrue();
    $this->assertAuthenticatedAs($customer, 'customer');
});

it('rejects registration with duplicate email in same tenant', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'email' => 'dup@example.com']);

    $this->post($base.'/register', [
        'name' => 'Another',
        'email' => 'dup@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');
});

it('allows same email in different tenants', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'tenant-a-'.uniqid()]);
    $baseA = authBase($tenantA);
    Customer::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'shared@example.com']);

    $tenantB = actingAsTenant(['subdomain' => 'tenant-b-'.uniqid()]);
    $baseB = authBase($tenantB);

    $this->post($baseB.'/register', [
        'name' => 'Shared User',
        'email' => 'shared@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('storefront.account.dashboard'));
    $this->assertDatabaseHas('customers', ['tenant_id' => $tenantB->id, 'email' => 'shared@example.com']);
});

it('logs in with valid credentials and updates last_login_at', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'email' => 'login@example.com', 'password' => Hash::make('secret123')]);
    $customer->update(['last_login_at' => null]);

    $response = $this->post($base.'/login', ['email' => 'login@example.com', 'password' => 'secret123']);
    $response->assertRedirect(route('storefront.account.dashboard'));
    $this->assertAuthenticatedAs($customer, 'customer');
    expect($customer->fresh()->last_login_at)->not->toBeNull();
});

it('rejects login with invalid password', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'email' => 'login2@example.com', 'password' => Hash::make('correct')]);

    $this->post($base.'/login', ['email' => 'login2@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest('customer');
});

it('prevents cross-tenant login', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'cross-a-'.uniqid()]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'cross@example.com', 'password' => Hash::make('secret123')]);

    $tenantB = actingAsTenant(['subdomain' => 'cross-b-'.uniqid()]);
    $baseB = authBase($tenantB);

    $this->post($baseB.'/login', ['email' => 'cross@example.com', 'password' => 'secret123'])->assertSessionHasErrors('email');
    $this->assertGuest('customer');
});

it('logs out and invalidates session', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($customer, 'customer');

    $response = $this->post($base.'/logout');
    $response->assertRedirect(route('storefront.home'));
    $this->assertGuest('customer');
});

it('requires authentication for protected account routes', function (): void {
    $tenant = actingAsTenant();
    $base = authBase($tenant);
    $this->get($base.'/account')->assertRedirect(route('storefront.login'));
    $this->get($base.'/account/orders')->assertRedirect(route('storefront.login'));
});
