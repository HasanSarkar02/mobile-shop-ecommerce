<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Domain;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\StaticPage;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\WelcomeTenantOwnerNotification;
use App\Services\Storefront\ProductCardData;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function canonicalTenantFixture(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'canonical-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $plan = Plan::query()->create([
        'name' => 'Canonical Plan '.Str::random(8),
        'slug' => 'canonical-'.Str::lower(Str::random(8)),
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => true,
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    return [$tenant, $owner, $plan];
}

function activeCanonicalDomain(Tenant $tenant, string $host): Domain
{
    return Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => $host,
        'normalized_domain' => $host,
        'status' => DomainStatus::Active,
        'verified_at' => now(),
    ]);
}

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'deployment.force_https' => true,
        'deployment.url_scheme' => 'http',
        'deployment.dedicated.tenant_id' => null,
        'deployment.dedicated.canonical_host' => null,
    ]);
    app(Tenancy::class)->set(null);
});

it('selects a valid primary custom domain and safely falls back to the SaaS subdomain', function (): void {
    [$tenant, $owner, $plan] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    $urls = app(TenantUrlGenerator::class);

    expect($urls->canonicalHost($tenant))->toBe('primary.example.test')
        ->and($urls->canonicalRoute($tenant, 'storefront.home'))->toBe('https://primary.example.test/');

    $primary->update(['status' => DomainStatus::Suspended]);
    expect($urls->canonicalHost($tenant))->toBe($tenant->subdomain.'.'.config('tenancy.central_domain'));

    $primary->update(['status' => DomainStatus::Active, 'verified_at' => null]);
    expect($urls->canonicalHost($tenant))->toBe($tenant->subdomain.'.'.config('tenancy.central_domain'));

    $primary->update(['verified_at' => now()]);
    $plan->update(['custom_domain_allowed' => false]);
    expect($urls->canonicalHost($tenant))->toBe($tenant->subdomain.'.'.config('tenancy.central_domain'));

    $tenantB = Tenant::factory()->create(['status' => 'active']);
    $domainB = activeCanonicalDomain($tenantB, 'other-tenant.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $domainB->id]);
    expect($urls->canonicalHost($tenant))->toBe($tenant->subdomain.'.'.config('tenancy.central_domain'));
});

it('uses the Dedicated canonical host and ignores SaaS primary state', function (): void {
    [$tenant] = canonicalTenantFixture();
    $domain = activeCanonicalDomain($tenant, 'saas-primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $domain->id]);

    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated.example.test',
    ]);

    expect(app(TenantUrlGenerator::class)->canonicalHost($tenant))->toBe('dedicated.example.test')
        ->and(app(TenantUrlGenerator::class)->canonicalPath($tenant, '/blog'))->toBe('https://dedicated.example.test/blog');
});

it('emits the primary canonical URL from an alias request without redirecting', function (): void {
    [$tenant] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'primary-page.example.test');
    $alias = activeCanonicalDomain($tenant, 'alias-page.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    app(Tenancy::class)->set($tenant);
    $page = StaticPage::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Canonical Page',
        'slug' => 'canonical-page',
        'content' => '<p>Canonical content</p>',
        'status' => 'published',
    ]);

    $primaryResponse = $this->get('http://'.$primary->domain.'/page/'.$page->slug);
    $aliasResponse = $this->get('http://'.$alias->domain.'/page/'.$page->slug);

    $canonical = 'https://'.$primary->domain.'/page/'.$page->slug;

    $primaryResponse->assertOk()->assertSee($canonical);
    $aliasResponse->assertOk()->assertSee($canonical);
});

it('uses the primary host for homepage, FAQ, blog, product JSON-LD, sitemap, and robots', function (): void {
    [$tenant] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'seo-primary.example.test');
    $alias = activeCanonicalDomain($tenant, 'seo-alias.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    app(Tenancy::class)->set($tenant);
    $faq = Faq::query()->create([
        'tenant_id' => $tenant->id,
        'question' => 'Question?',
        'answer' => 'Answer.',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    ProductVariant::factory()->for($product)->create(['tenant_id' => $tenant->id]);
    BlogPost::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Canonical Blog',
        'slug' => 'canonical-blog',
        'content' => '<p>Blog content</p>',
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get('http://'.$alias->domain.'/')->assertOk()->assertSee('https://'.$primary->domain.'/');
    $this->get('http://'.$alias->domain.'/faq')->assertOk()->assertSee('https://'.$primary->domain.'/faq');
    $this->get('http://'.$alias->domain.'/blog')->assertOk()->assertSee('https://'.$primary->domain.'/blog');
    $this->get('http://'.$alias->domain.'/product/'.$product->translation('en')->slug)
        ->assertOk()
        ->assertSee('"url":"https://'.$primary->domain.'/product/'.$product->translation('en')->slug.'"', false);

    Cache::flush();
    $sitemap = $this->get('http://'.$alias->domain.'/sitemap.xml')->assertOk();
    $robots = $this->get('http://'.$alias->domain.'/robots.txt')->assertOk();
    $sitemap->assertSee('https://'.$primary->domain.'/');
    $sitemap->assertDontSee('https://'.$alias->domain.'/');
    $robots->assertSee('Sitemap: https://'.$primary->domain.'/sitemap.xml');
});

it('uses a new sitemap cache key when the primary domain changes', function (): void {
    [$tenant] = canonicalTenantFixture();
    $first = activeCanonicalDomain($tenant, 'first-primary.example.test');
    $second = activeCanonicalDomain($tenant, 'second-primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $first->id]);

    $this->get('http://'.$first->domain.'/sitemap.xml')->assertOk()->assertSee('https://'.$first->domain.'/');

    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $second->id]);
    $changed = $this->get('http://'.$second->domain.'/sitemap.xml')->assertOk();

    $changed->assertSee('https://'.$second->domain.'/');
    $changed->assertDontSee('https://'.$first->domain.'/');
});

it('emits the primary canonical host for category, brand, and collection pages', function (): void {
    [$tenant] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'taxonomy-primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    app(Tenancy::class)->set($tenant);
    $category = Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Canonical Category', 'slug' => 'canonical-category']);
    $brand = Brand::query()->create(['tenant_id' => $tenant->id, 'name' => 'Canonical Brand', 'slug' => 'canonical-brand']);
    $collection = Collection::query()->create(['tenant_id' => $tenant->id, 'name' => 'Canonical Collection', 'slug' => 'canonical-collection', 'is_active' => true]);

    $this->get('http://'.$primary->domain.'/category/'.$category->slug)->assertOk()->assertSee('https://'.$primary->domain.'/category/'.$category->slug);
    $this->get('http://'.$primary->domain.'/brand/'.$brand->slug)->assertOk()->assertSee('https://'.$primary->domain.'/brand/'.$brand->slug);
    $this->get('http://'.$primary->domain.'/collection/'.$collection->slug)->assertOk()->assertSee('https://'.$primary->domain.'/collection/'.$collection->slug);
});

it('uses the primary host for ProductCardData public URLs', function (): void {
    [$tenant] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'card-primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);
    app(Tenancy::class)->set($tenant);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    ProductTranslation::factory()->for($product)->create(['tenant_id' => $tenant->id, 'locale' => 'en']);
    $product->load(['translations', 'variants', 'media', 'emiPlans']);

    $card = app(ProductCardData::class)->forProduct($product);

    expect($card['url'])->toBe('https://'.$primary->domain.'/product/'.$product->translation('en')->slug);
});

it('uses the primary host for durable welcome admin URLs', function (): void {
    [$tenant, $owner] = canonicalTenantFixture();
    $primary = activeCanonicalDomain($tenant, 'welcome-primary.example.test');
    $tenant->newQuery()->whereKey($tenant->id)->update(['primary_domain_id' => $primary->id]);

    $message = (new WelcomeTenantOwnerNotification($tenant))->toMail($owner);

    expect($message->actionUrl)->toBe('https://'.$primary->domain.'/admin');
});
