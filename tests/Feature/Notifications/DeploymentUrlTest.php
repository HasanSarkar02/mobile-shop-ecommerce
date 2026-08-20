<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\WelcomeTenantOwnerNotification;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Notifications\Messages\MailMessage;

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.force_https' => true,
        'deployment.url_scheme' => 'http',
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);
});

it('generates HTTPS SaaS tenant and Platform URLs', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'url-shop']);
    $urls = app(TenantUrlGenerator::class);

    expect($urls->storefront($tenant, '/auto-login/1?signature=test'))
        ->toBe('https://url-shop.'.config('tenancy.central_domain').'/auto-login/1?signature=test')
        ->and($urls->admin($tenant))
        ->toBe('https://url-shop.'.config('tenancy.central_domain').'/admin')
        ->and($urls->platform('/platform/plan-change-requests'))
        ->toBe('https://'.config('tenancy.central_domain').'/platform/plan-change-requests');
});

it('generates the configured Dedicated tenant host', function (): void {
    $tenant = Tenant::factory()->create();

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated-url.example.test',
    ]);

    expect(app(TenantUrlGenerator::class)->admin($tenant))
        ->toBe('https://dedicated-url.example.test/admin');
});

it('renders the welcome notification without the removed trial_ends_at field', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'welcome-url-shop']);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    expect((new WelcomeTenantOwnerNotification($tenant))->toMail($owner))
        ->toBeInstanceOf(MailMessage::class);
});
