<?php

use App\Http\Controllers\Storefront\Account\AccountAddressController;
use App\Http\Controllers\Storefront\Account\AccountOrderController;
use App\Http\Controllers\Storefront\Account\AccountProfileController;
use App\Http\Controllers\Storefront\Auth\CustomerAuthController;
use App\Http\Controllers\Storefront\AutoLoginController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\BrandController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CollectionController;
use App\Http\Controllers\Storefront\CompareController;
use App\Http\Controllers\Storefront\FaqController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\NewsletterController;
use App\Http\Controllers\Storefront\OrderTrackingController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ProductReviewController;
use App\Http\Controllers\Storefront\RobotsController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SearchSuggestController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\StaticPageController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Livewire\CheckoutPage;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('storefront.home');
Route::get('/category', [CategoryController::class, 'index'])->name('storefront.categories.index');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('storefront.category');
Route::get('/brand', [BrandController::class, 'index'])->name('storefront.brands.index');
Route::get('/brand/{slug}', [BrandController::class, 'show'])->name('storefront.brand');
Route::get('/collection/{slug}', [CollectionController::class, 'show'])->name('storefront.collection');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('storefront.product');
Route::get('/search', [SearchController::class, 'index'])->name('storefront.search');
Route::get('/search/suggest', SearchSuggestController::class)->name('storefront.search.suggest');
Route::get('/page/{slug}', [StaticPageController::class, 'show'])->name('storefront.page');

Route::get('/compare', [CompareController::class, 'show'])->name('storefront.compare');
Route::post('/compare/toggle', [CompareController::class, 'toggle'])->name('storefront.compare.toggle');
Route::post('/compare/remove', [CompareController::class, 'remove'])->name('storefront.compare.remove');
Route::post('/compare/clear', [CompareController::class, 'clear'])->name('storefront.compare.clear');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('storefront.wishlist');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('storefront.wishlist.toggle');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('storefront.newsletter.subscribe');

Route::post('/cart', [CartController::class, 'store'])->name('storefront.cart.store');
Route::get('/cart', [CartController::class, 'show'])->name('storefront.cart');
Route::get('/checkout', CheckoutPage::class)->name('storefront.checkout');
Route::get('/checkout/confirmation/{orderNumber}', function (string $orderNumber) {
    $order = Order::query()
        ->with('items')
        ->where('order_number', $orderNumber)
        ->first();

    return view('storefront.checkout.confirmation', compact('orderNumber', 'order'));
})->name('storefront.checkout.confirmation');

Route::get('/track-order', [OrderTrackingController::class, 'form'])->name('storefront.track-order.form');
Route::post('/track-order', [OrderTrackingController::class, 'show'])->name('storefront.track-order.show');

Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('storefront.login');
Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('storefront.register');
Route::post('/register', [CustomerAuthController::class, 'register'])->name('storefront.register.submit');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('storefront.logout');
Route::get('/auto-login/{user}', AutoLoginController::class)->name('storefront.auto-login');

Route::get('/faq', [FaqController::class, 'index'])->name('storefront.faq');
Route::get('/blog', [BlogController::class, 'index'])->name('storefront.blog');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('storefront.blog.show');
Route::post('/product/{product}/reviews', [ProductReviewController::class, 'store'])
    ->middleware('auth:customer')
    ->name('storefront.product.reviews.store');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('storefront.sitemap');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('storefront.robots');

Route::middleware('auth:customer')->prefix('account')->name('storefront.account.')->group(function (): void {
    Route::get('/', fn () => view('storefront.account.dashboard'))->name('dashboard');
    Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    Route::get('/addresses', [AccountAddressController::class, 'index'])->name('addresses');
    Route::post('/addresses', [AccountAddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [AccountAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AccountAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('/profile', [AccountProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [AccountProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [AccountProfileController::class, 'updatePassword'])->name('password.update');
});

Route::get('/payment/{order}/pay', [PaymentController::class, 'pay'])->name('storefront.payment.pay');
Route::post('/payment/success', [PaymentController::class, 'success'])->name('storefront.payment.success');
Route::post('/payment/fail', [PaymentController::class, 'fail'])->name('storefront.payment.fail');
Route::post('/payment/cancel', [PaymentController::class, 'cancel'])->name('storefront.payment.cancel');
Route::post('/payment/ipn', [PaymentController::class, 'ipn'])->name('storefront.payment.ipn');
