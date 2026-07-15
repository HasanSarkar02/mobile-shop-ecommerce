<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Services\InventoryService;

class CartService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function getOrCreateCart(?Customer $customer, ?string $sessionToken): Cart
    {
        $query = Cart::query()->whereNull('converted_at');

        if ($customer) {
            $cart = (clone $query)->where('customer_id', $customer->id)->first();
        } else {
            $cart = (clone $query)->where('session_token', $sessionToken)->first();
        }

        return $cart ?? Cart::query()->create([
            'tenant_id' => tenant()->id,
            'customer_id' => $customer?->id,
            'session_token' => $customer ? null : $sessionToken,
            'currency_code' => tenant()->currency,
        ]);
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        if (! $this->inventory->isPurchasable($variant, $quantity)) {
            throw new \RuntimeException("'{$variant->sku}' is not available in the requested quantity.");
        }

        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item) {
            $item->update(['quantity' => $item->quantity + $quantity, 'unit_price' => $variant->price]);

            return $item;
        }

        return $cart->items()->create([
            'tenant_id' => $cart->tenant_id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function markConverted(Cart $cart): void
    {
        $cart->update(['converted_at' => now()]);
    }
}