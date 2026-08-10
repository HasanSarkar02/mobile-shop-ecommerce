<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Session;

/**
 * Session-based only, deliberately no database table — comparison lists are
 * short-lived and disposable, not worth persisting.
 */
class CompareService
{
    private const SESSION_KEY = 'compare_product_ids';
    private const LIMIT = 4;

    public function ids(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function toggle(int $productId): bool
    {
        $ids = $this->ids();

        if (in_array($productId, $ids, true)) {
            Session::put(self::SESSION_KEY, array_values(array_diff($ids, [$productId])));

            return false;
        }

        if (count($ids) >= self::LIMIT) {
            throw new \RuntimeException('You can compare up to '.self::LIMIT.' products at a time.');
        }

        $ids[] = $productId;
        Session::put(self::SESSION_KEY, $ids);

        return true;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}