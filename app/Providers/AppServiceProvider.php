<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderPaymentRecorded;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderCancelledNotifications;
use App\Listeners\SendOrderPlacedNotifications;
use App\Listeners\SendOrderStatusChangedNotifications;
use App\Listeners\SendPaymentRecordedNotifications;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductReview;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StaticPage;
use App\Models\Tenant;
use App\Observers\BlogPostObserver;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\CollectionObserver;
use App\Observers\ProductAttributeValueObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductReviewObserver;
use App\Observers\ProductTranslationObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\SerialNumberObserver;
use App\Observers\StaticPageObserver;
use App\Observers\TenantObserver;
use App\Support\Tenancy\Tenancy;
use App\View\Composers\StorefrontLayoutComposer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        $this->app->scoped(Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
        Tenant::observe(TenantObserver::class);
        SerialNumber::observe(SerialNumberObserver::class);
        ProductReview::observe(ProductReviewObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        Paginator::defaultView('components.ui.pagination');
        Relation::morphMap([
            'product' => Product::class,
            'category' => Category::class,
            'brand' => Brand::class,
            'collection' => Collection::class,
            'static_page' => StaticPage::class,
            'blog_post' => BlogPost::class,
        ]);

        Category::observe(CategoryObserver::class);
        Brand::observe(BrandObserver::class);
        Collection::observe(CollectionObserver::class);
        StaticPage::observe(StaticPageObserver::class);
        ProductTranslation::observe(ProductTranslationObserver::class);
        ProductAttributeValue::observe(ProductAttributeValueObserver::class);

        Event::listen(OrderPlaced::class, SendOrderPlacedNotifications::class);
        Event::listen(OrderStatusChanged::class, SendOrderStatusChangedNotifications::class);
        Event::listen(OrderCancelled::class, SendOrderCancelledNotifications::class);
        Event::listen(OrderPaymentRecorded::class, SendPaymentRecordedNotifications::class);

        View::composer(
            'storefront.layout',
            StorefrontLayoutComposer::class,
        );
    }
}
