<?php

declare(strict_types=1);

use App\Models\HomepageSection;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function sortingTenant(string $subdomain): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain]);
}

it('orders homepage sections by sort_order ascending', function (): void {
    $tenant = sortingTenant('homepage-sort');
    app(Tenancy::class)->set($tenant);

    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Lowest Priority Section',
        'type' => 'newsletter_cta',
        'is_active' => true,
        'sort_order' => 10,
    ]);

    HomepageSection::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Highest Priority Section',
        'type' => 'newsletter_cta',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = get('http://homepage-sort.'.config('tenancy.central_domain').'/')->assertOk();

    // Check that 'Highest Priority Section' appears before 'Lowest Priority Section'
    $html = $response->getContent();

    $posHighest = strpos($html, 'Highest Priority Section');
    $posLowest = strpos($html, 'Lowest Priority Section');

    expect($posHighest)->toBeGreaterThan(0)
        ->and($posLowest)->toBeGreaterThan(0)
        ->and($posHighest)->toBeLessThan($posLowest);
});
