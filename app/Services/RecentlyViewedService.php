<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use Illuminate\Support\Facades\Cookie;

class RecentlyViewedService
{
    private const COOKIE_BASE = 'recently_viewed';

    private function cookieName(): string
    {
        $tenantId = tenant()?->id;

        return $tenantId ? self::COOKIE_BASE.'_'.$tenantId : self::COOKIE_BASE;
    }

    public function record(Product $product, ?Customer $customer): void
    {
        $limit = (int) config('catalog.recently_viewed_limit');

        if ($customer) {
            RecentlyViewedProduct::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'product_id' => $product->id],
                ['tenant_id' => $customer->tenant_id, 'viewed_at' => now()],
            );

            $staleIds = RecentlyViewedProduct::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('viewed_at')
                ->offset($limit)
                ->limit(PHP_INT_MAX)
                ->pluck('id');

            if ($staleIds->isNotEmpty()) {
                RecentlyViewedProduct::query()->whereIn('id', $staleIds)->delete();
            }

            return;
        }

        $ids = $this->guestIds();
        $ids = array_values(array_unique(array_merge([$product->id], $ids)));
        $ids = array_slice($ids, 0, $limit);

        Cookie::queue($this->cookieName(), implode(',', $ids), 60 * 24 * 30);
    }

    public function recentProductIds(?Customer $customer): array
    {
        if ($customer) {
            return RecentlyViewedProduct::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('viewed_at')
                ->pluck('product_id')
                ->all();
        }

        return $this->guestIds();
    }

    private function guestIds(): array
    {
        $raw = request()->cookie($this->cookieName());
        // Backward compat: fall back to base cookie name for existing tests / legacy guests
        if ($raw === null || $raw === '') {
            $raw = request()->cookie(self::COOKIE_BASE);
        }

        return $raw ? array_filter(array_map('intval', explode(',', $raw))) : [];
    }

    public function clear(?Customer $customer): void
    {
        if ($customer) {
            RecentlyViewedProduct::query()->where('customer_id', $customer->id)->delete();

            return;
        }
        Cookie::queue(Cookie::forget($this->cookieName()));
    }

    public function mergeGuestIntoCustomer(Customer $customer): void
    {
        $guestIds = $this->guestIds();
        if ($guestIds === []) {
            return;
        }
        foreach ($guestIds as $pid) {
            RecentlyViewedProduct::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'product_id' => $pid],
                ['tenant_id' => $customer->tenant_id, 'viewed_at' => now()],
            );
        }
        Cookie::queue(Cookie::forget($this->cookieName()));
    }
}
