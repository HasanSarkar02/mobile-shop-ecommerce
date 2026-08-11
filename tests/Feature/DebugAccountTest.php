<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

it('debug: customer can login and reach account dashboard', function () {
    $tenant = Tenant::factory()->create(['subdomain' => 'demo', 'status' => 'active']);
    app(Tenancy::class)->set($tenant);
    Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'cust@demo.test',
        'password' => bcrypt('password'),
    ]);
    app(Tenancy::class)->set(null);

    $base = 'http://demo.mobile-shop-ecommerce.test';

    $login = $this->post($base . '/login', [
        'email' => 'cust@demo.test',
        'password' => 'password',
    ]);

    dump('login status: ' . $login->getStatusCode());
    dump('login exception: ' . ($login->exception ? get_class($login->exception) . ': ' . $login->exception->getMessage() : 'none'));

    $response = $this->get($base . '/account');
    dump('account status: ' . $response->getStatusCode());
    dump('account exception: ' . ($response->exception ? get_class($response->exception) . ': ' . $response->exception->getMessage() : 'none'));
});
