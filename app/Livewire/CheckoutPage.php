<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrderSource;
use App\Exceptions\CartAlreadyConvertedException;
use App\Exceptions\ReservationLimitExceededException;
use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('storefront.layout')]
class CheckoutPage extends Component
{
    public array $issues = [];

    public ?int $selectedAddressId = null;

    public array $guestAddress = ['recipient_name' => '', 'phone' => '', 'address_line_1' => '', 'city' => ''];

    public string $guestName = '';

    public string $guestEmail = '';

    public string $guestPhone = '';

    public ?int $shippingMethodId = null;

    public ?int $paymentMethodId = null;

    public ?string $customerNote = null;

    public bool $preorder_ack = false;

    // Secondary, UX-only guard against a rapid double-click sending two
    // near-simultaneous requests before the wire:loading disabled state (see
    // the checkout button markup) takes effect. The authoritative protection
    // against double order creation is the cart row lock in
    // OrderService::createFromCart() — this flag alone does not stop two
    // different browser tabs/sessions from racing each other.
    public bool $isPlacingOrder = false;

    public function mount(CartService $carts): void
    {
        $cart = $carts->getOrCreateCart(Auth::guard('customer')->user(), request()->cookie('cart_token'));
        $result = $carts->revalidate($cart);
        $this->issues = $result['issues']->all();
    }

    public function placeOrder(CartService $carts, OrderService $orders): void
    {
        if ($this->isPlacingOrder) {
            return;
        }

        if (RateLimiter::tooManyAttempts('place-order:'.request()->ip(), 5)) {
            $this->issues = ['Too many attempts. Please wait a moment and try again.'];

            return;
        }
        RateLimiter::hit('place-order:'.request()->ip(), 60);

        $this->isPlacingOrder = true;

        try {
            $customer = Auth::guard('customer')->user();
            $cart = $carts->getOrCreateCart($customer, request()->cookie('cart_token'));

            $revalidation = $carts->revalidate($cart);
            $this->issues = $revalidation['issues']->all();

            if ($this->issues !== []) {
                return;
            }

            $shipping = ShippingMethod::query()->find($this->shippingMethodId);

            $cart->loadMissing('items.variant');
            $hasPreorder = $cart->items->contains(fn ($item) => $item->variant?->fulfillment_strategy?->value === 'preorder');
            if ($hasPreorder && ! $this->preorder_ack) {
                $this->issues = ['Please acknowledge that pre-order items ship around their expected availability date.'];

                return;
            }

            $orderData = [
                'shipping_method_id' => $this->shippingMethodId,
                'payment_method_id' => $this->paymentMethodId,
                'shipping_cost' => $shipping?->cost ?? 0,
                'customer_note' => $this->customerNote,
                'preorder_ack_at' => $hasPreorder && $this->preorder_ack ? now() : null,
            ];

            if ($customer) {
                $address = Address::query()->findOrFail($this->selectedAddressId);
                abort_unless($address->customer_id === $customer->id, 403);

                $orderData['shipping_address_id'] = $address->id;
                $orderData['shipping_address'] = $address->only([
                    'recipient_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'area', 'postal_code', 'country',
                ]);
            } else {
                $this->validate([
                    'guestName' => ['required', 'string'],
                    'guestEmail' => ['required', 'email'],
                    'guestPhone' => ['required', 'string'],
                    'guestAddress.recipient_name' => ['required', 'string'],
                    'guestAddress.phone' => ['required', 'string'],
                    'guestAddress.address_line_1' => ['required', 'string'],
                    'guestAddress.city' => ['required', 'string'],
                ]);

                $orderData['guest_name'] = $this->guestName;
                $orderData['guest_email'] = $this->guestEmail;
                $orderData['guest_phone'] = $this->guestPhone;
                $orderData['shipping_address'] = $this->guestAddress;
            }

            try {
                $order = $orders->createFromCart($cart, $orderData, $customer ? OrderSource::Website : OrderSource::Website);
            } catch (CartAlreadyConvertedException) {
                // Another submission for this same cart already went through
                // (e.g. a double-click). Send the customer to their confirmed
                // order instead of showing an error for something that actually
                // succeeded.
                $this->redirect(route('storefront.checkout'), navigate: false);

                return;
            } catch (ReservationLimitExceededException $e) {
                $this->issues = [$e->getMessage()];

                return;
            }

            if ($order->paymentMethod?->gateway_driver) {
                $this->redirect(route('storefront.payment.pay', $order), navigate: false);

                return;
            }
            $this->redirect(route('storefront.checkout.confirmation', $order->order_number), navigate: false);
        } finally {
            $this->isPlacingOrder = false;
        }
    }

    public function render(CartService $carts, CouponService $coupons)
    {
        $customer = Auth::guard('customer')->user();
        $cart = $carts->getOrCreateCart($customer, request()->cookie('cart_token'));
        $cart->load('items.variant.product.translations', 'items.variant.media');
        $subtotal = $cart->items->sum(fn ($item) => $item->lineTotal());
        $couponResult = $coupons->computeForCart($cart, $customer);
        $shipping = ShippingMethod::query()->find($this->shippingMethodId);

        $hasPreorder = $cart->items->contains(fn ($item) => $item->variant?->fulfillment_strategy?->value === 'preorder');
        $hasStock = $cart->items->contains(fn ($item) => $item->variant?->fulfillment_strategy?->value === 'stock');
        $isMixed = $hasPreorder && $hasStock;
        $preorderEta = null;
        if ($hasPreorder) {
            $preorderEta = $cart->items
                ->filter(fn ($item) => $item->variant?->fulfillment_strategy?->value === 'preorder' && $item->variant?->expected_available_at)
                ->map(fn ($item) => $item->variant->expected_available_at)
                ->sort()
                ->first();
        }

        return view('livewire.checkout-page', [
            'customer' => $customer,
            'addresses' => $customer ? Address::query()->where('customer_id', $customer->id)->get() : collect(),
            'shippingMethods' => ShippingMethod::query()->where('is_active', true)->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->get(),
            'cartItems' => $cart->items,
            'subtotal' => $subtotal,
            'discount' => $couponResult->valid ? $couponResult->discountAmount : 0,
            'shippingCost' => $shipping?->cost ?? 0,
            'hasPreorder' => $hasPreorder,
            'isMixed' => $isMixed,
            'preorderEta' => $preorderEta,
        ]);
    }
}
