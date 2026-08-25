<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Hash;

function accountBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

it('allows authenticated customer to access dashboard and profile', function (): void {
    $tenant = actingAsTenant();
    $base = accountBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($customer, 'customer')->get($base.'/account')->assertOk()->assertSee($customer->email);
    $this->actingAs($customer, 'customer')->get($base.'/account/profile')->assertOk()->assertSee($customer->name);
});

it('isolates orders between tenants and between customers same tenant', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'acct-a-'.uniqid()]);
    $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $customerA2 = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $orderA = Order::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA->id, 'grand_total' => 10000, 'status' => 'pending']);
    $orderA2 = Order::factory()->create(['tenant_id' => $tenantA->id, 'customer_id' => $customerA2->id, 'grand_total' => 20000, 'status' => 'pending']);

    $tenantB = actingAsTenant(['subdomain' => 'acct-b-'.uniqid()]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $orderB = Order::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id, 'grand_total' => 30000, 'status' => 'pending']);

    // Tenant A customer sees only own order in index
    app(Tenancy::class)->set($tenantA);
    $baseA = accountBase($tenantA);
    $htmlA = $this->actingAs($customerA, 'customer')->get($baseA.'/account/orders')->assertOk()->getContent();
    expect($htmlA)->toContain($orderA->order_number)
        ->not->toContain($orderA2->order_number)
        ->not->toContain($orderB->order_number);

    // Direct show of other customer's same-tenant order -> 404
    $this->actingAs($customerA, 'customer')->get($baseA.'/account/orders/'.$orderA2->id)->assertNotFound();

    // Cross-tenant order -> 404 (tenant scoping + customer check)
    app(Tenancy::class)->set($tenantB);
    $baseB = accountBase($tenantB);
    $this->actingAs($customerB, 'customer')->get($baseB.'/account/orders/'.$orderA->id)->assertNotFound();
});

it('allows customer to manage own addresses with tenant isolation', function (): void {
    $tenant = actingAsTenant();
    $base = accountBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    // Create
    $this->actingAs($customer, 'customer')->post($base.'/account/addresses', [
        'label' => 'Home',
        'type' => 'shipping',
        'recipient_name' => 'Test User',
        'phone' => '01711111111',
        'address_line_1' => '123 Street',
        'city' => 'Dhaka',
        'is_default' => false,
    ])->assertRedirect();
    $this->assertDatabaseHas('addresses', ['customer_id' => $customer->id, 'tenant_id' => $tenant->id, 'city' => 'Dhaka']);

    $address = Address::query()->where('customer_id', $customer->id)->firstOrFail();

    // Update own
    $this->actingAs($customer, 'customer')->put($base.'/account/addresses/'.$address->id, [
        'label' => 'Home',
        'type' => 'shipping',
        'recipient_name' => 'Test User',
        'phone' => '01711111111',
        'address_line_1' => '456 Avenue',
        'city' => 'Chittagong',
    ])->assertRedirect();
    expect($address->fresh()->city)->toBe('Chittagong');

    // Cross-customer same tenant cannot update
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($otherCustomer, 'customer')->put($base.'/account/addresses/'.$address->id, [
        'label' => 'Home', 'type' => 'shipping', 'recipient_name' => 'Hacker', 'phone' => '01711111111', 'address_line_1' => 'Hacked', 'city' => 'Hacked',
    ])->assertNotFound();

    // Cross-tenant cannot delete
    $tenantB = actingAsTenant(['subdomain' => 'addr-b-'.uniqid()]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $baseB = accountBase($tenantB);
    $this->actingAs($customerB, 'customer')->delete($baseB.'/account/addresses/'.$address->id)->assertNotFound();

    // Delete own
    app(Tenancy::class)->set($tenant);
    $this->actingAs($customer, 'customer')->delete($base.'/account/addresses/'.$address->id)->assertRedirect();
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
});

it('allows customer to update profile and password', function (): void {
    $tenant = actingAsTenant();
    $base = accountBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name', 'phone' => '01700000000', 'password' => Hash::make('oldpass123')]);

    // Profile
    $this->actingAs($customer, 'customer')->put($base.'/account/profile', ['name' => 'New Name', 'phone' => '01799999999'])->assertRedirect()->assertSessionHas('status');
    expect($customer->fresh()->name)->toBe('New Name');
    expect($customer->fresh()->phone)->toBe('01799999999');

    // Password with correct current
    $this->actingAs($customer, 'customer')->put($base.'/account/password', [
        'current_password' => 'oldpass123',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertRedirect()->assertSessionHas('status');
    expect(Hash::check('newpass123', $customer->fresh()->password))->toBeTrue();

    // Password with wrong current
    $this->actingAs($customer, 'customer')->put($base.'/account/password', [
        'current_password' => 'wrong',
        'password' => 'another123',
        'password_confirmation' => 'another123',
    ])->assertSessionHasErrors('current_password');
});

it('prevents unauthenticated access to account orders show', function (): void {
    $tenant = actingAsTenant();
    $base = accountBase($tenant);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

    $this->get($base.'/account/orders/'.$order->id)->assertRedirect(route('storefront.login'));
});
