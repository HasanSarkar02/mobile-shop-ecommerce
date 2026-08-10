<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound `scoped`, not `singleton`: a `singleton` instance survives for the
        // lifetime of a long-running worker process (queue:work, Octane) and would
        // leak one job's/request's tenant into the next. `scoped` is reset by the
        // framework between queued jobs and between Octane requests, so every job
        // starts from a clean (no tenant) state and must explicitly restore its own
        // tenant context (see App\Jobs\SendNotificationJob for the pattern).
        $this->app->scoped(\App\Support\Tenancy\Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\ProductVariant::observe(\App\Observers\ProductVariantObserver::class);
        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);
        \App\Models\SerialNumber::observe(\App\Observers\SerialNumberObserver::class);
        \App\Models\ProductReview::observe(\App\Observers\ProductReviewObserver::class);
        \App\Models\BlogPost::observe(\App\Observers\BlogPostObserver::class);
        \Illuminate\Pagination\Paginator::defaultView('components.ui.pagination');
        Relation::morphMap([
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'brand' => \App\Models\Brand::class,
            'collection' => \App\Models\Collection::class,
            'static_page' => \App\Models\StaticPage::class,
            'blog_post' => \App\Models\BlogPost::class,
        ]);

        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Brand::observe(\App\Observers\BrandObserver::class);
        \App\Models\Collection::observe(\App\Observers\CollectionObserver::class);
        \App\Models\StaticPage::observe(\App\Observers\StaticPageObserver::class);
        \App\Models\ProductTranslation::observe(\App\Observers\ProductTranslationObserver::class);
        

        Event::listen(\App\Events\OrderPlaced::class, \App\Listeners\SendOrderPlacedNotifications::class);
        Event::listen(\App\Events\OrderStatusChanged::class, \App\Listeners\SendOrderStatusChangedNotifications::class);
        Event::listen(\App\Events\OrderCancelled::class, \App\Listeners\SendOrderCancelledNotifications::class);
        Event::listen(\App\Events\OrderPaymentRecorded::class, \App\Listeners\SendPaymentRecordedNotifications::class);

        \Illuminate\Support\Facades\View::composer(
            'storefront.layout',
            \App\View\Composers\StorefrontLayoutComposer::class,
        );
    }
}