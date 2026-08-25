<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\StoreThemeSetting;
use App\Services\IndustrySeeders\IndustrySeederService;
use App\Support\Tenancy\Tenancy;
use App\Support\ThemePresets;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('seeds electronics palette with precise hex codes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Electronics->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#2563eb')
        ->and($settings->secondary_color)->toBe('#1d4ed8');
});

it('seeds furniture palette with precise hex codes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Furniture->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#78350f')
        ->and($settings->secondary_color)->toBe('#451a03');
});

it('seeds grocery palette with precise hex codes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Grocery->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#22c55e')
        ->and($settings->secondary_color)->toBe('#15803d');
});

it('seeds fashion palette with precise hex codes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Fashion->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#111827')
        ->and($settings->secondary_color)->toBe('#000000');
});

it('seeds sports palette with precise hex codes', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Sports->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#dc2626')
        ->and($settings->secondary_color)->toBe('#991b1b');
});

it('seeds general fallback palette', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::General->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($settings->primary_color)->toBe('#16a34a')
        ->and($settings->secondary_color)->toBe('#15803d');
});

it('is idempotent and never overwrites owner custom colors', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Electronics->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $settings->update(['primary_color' => '#123456', 'secondary_color' => '#654321']);

    app(IndustrySeederService::class)->seed($tenant);

    $fresh = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($fresh->primary_color)->toBe('#123456')
        ->and($fresh->secondary_color)->toBe('#654321');
});

it('backfills null secondary to preset without touching custom primary', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Furniture->value]);

    $settings = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $settings->update(['primary_color' => '#abcdef', 'secondary_color' => null]);

    app(IndustrySeederService::class)->seed($tenant);

    $fresh = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($fresh->primary_color)->toBe('#abcdef')
        ->and($fresh->secondary_color)->toBe('#451a03');
});

it('resolves brand secondary fallback chain via model helpers', function (): void {
    $onlyPrimary = new StoreThemeSetting(['primary_color' => '#ff0000', 'secondary_color' => null]);
    expect($onlyPrimary->brandSecondaryColor())->toBe('#ff0000');

    $emptyBoth = new StoreThemeSetting(['primary_color' => null, 'secondary_color' => null]);
    expect($emptyBoth->brandColor())->toBe('#16a34a')
        ->and($emptyBoth->brandSecondaryColor())->toBe('#15803d');

    $customSecondary = new StoreThemeSetting(['primary_color' => '#ff0000', 'secondary_color' => '#00ff00']);
    expect($customSecondary->brandSecondaryColor())->toBe('#00ff00');
});

it('maps font stacks correctly', function (): void {
    expect(ThemePresets::fontStack('poppins'))->toContain('Poppins')
        ->and(ThemePresets::fontStack('roboto'))->toContain('Roboto')
        ->and(ThemePresets::fontStack(null))->toContain('Instrument Sans')
        ->and(theme_font_stack('inter'))->toContain('Instrument Sans');
});

it('renders layout-level CSS variables with fallback chain', function (): void {
    $tenant = actingAsTenant([
        'industry' => TenantIndustry::Electronics->value,
        'subdomain' => 'theme-css-'.uniqid(),
    ]);
    app(Tenancy::class)->set($tenant);

    $subdomain = $tenant->subdomain;

    $response = get('http://'.$subdomain.'.'.config('tenancy.central_domain').'/')->assertOk();
    $html = $response->getContent();

    // Inline style must contain both --brand and --brand-secondary with seeded hex
    expect($html)->toContain('--brand: #2563eb')
        ->and($html)->toContain('--brand-secondary: #1d4ed8');

    // Footer is de-branded to neutral surface (not bg-[var(--brand)])
    expect($html)->toContain('bg-gray-50')
        ->and($html)->toContain('dark:bg-gray-950')
        ->and($html)->not->toContain('footer class="border-t border-black/10 mt-20 bg-[var(--brand)]');
});
