<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrderSource;
use App\Enums\ShippingMethodType;
use App\Exceptions\CartAlreadyConvertedException;
use App\Exceptions\ReservationLimitExceededException;
use App\Models\Address;
use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Models\TenantShippingRate;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\ShippingService;
use Illuminate\Support\Collection;
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

    public ?int $bd_division_id = null;

    public ?int $bd_district_id = null;

    public ?int $bd_upazila_id = null;

    /** @var Collection<int, BdDivision> */
    public Collection $divisions;

    /** @var Collection<int, BdDistrict> */
    public Collection $districts;

    /** @var Collection<int, BdUpazila> */
    public Collection $upazilas;

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

        $this->divisions = BdDivision::query()->orderBy('name_en')->get();
        $this->districts = collect();
        $this->upazilas = collect();
    }

    public function updatedBdDivisionId(?int $value): void
    {
        $this->bd_district_id = null;
        $this->bd_upazila_id = null;
        $this->districts = $value ? BdDistrict::query()->where('division_id', $value)->orderBy('name_en')->get() : collect();
        $this->upazilas = collect();
        // Sync to guestAddress for snapshot persistence
        $this->guestAddress['bd_division_id'] = $value;
        $this->guestAddress['bd_district_id'] = null;
        $this->guestAddress['bd_upazila_id'] = null;
    }

    public function updatedBdDistrictId(?int $value): void
    {
        $this->bd_upazila_id = null;
        $this->upazilas = $value ? BdUpazila::query()->where('district_id', $value)->orderBy('name_en')->get() : collect();
        $this->guestAddress['bd_district_id'] = $value;
        $this->guestAddress['bd_upazila_id'] = null;
    }

    public function updatedBdUpazilaId(?int $value): void
    {
        $this->guestAddress['bd_upazila_id'] = $value;
    }

    public function updatedSelectedAddressId(?int $value): void
    {
        if ($value !== null) {
            $address = Address::query()->find($value);
            if ($address !== null) {
                $this->bd_division_id = $address->bd_division_id;
                $this->bd_district_id = $address->bd_district_id;
                $this->bd_upazila_id = $address->bd_upazila_id;
                $this->districts = $this->bd_division_id ? BdDistrict::query()->where('division_id', $this->bd_division_id)->orderBy('name_en')->get() : collect();
                $this->upazilas = $this->bd_district_id ? BdUpazila::query()->where('district_id', $this->bd_district_id)->orderBy('name_en')->get() : collect();
            }
        }
    }

    public function placeOrder(CartService $carts, OrderService $orders, ShippingService $shippingService): void
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

            // Unified priority: 1.Method Free/Pickup ->0, 2.Coupon FreeShipping ->0, 3.Geo free_threshold (post-discount) ->0, 4.Geo charge
            $cart->load('items');
            $subtotalForPricing = $cart->items->sum(fn ($item) => $item->lineTotal());
            $couponForShipping = app(CouponService::class)->computeForCart($cart, $customer);
            $discountForShipping = $couponForShipping->valid ? $couponForShipping->discountAmount : 0;
            $subtotalAfterDiscountForShipping = max(0, $subtotalForPricing - $discountForShipping);
            $isCouponFree = $couponForShipping->valid && $couponForShipping->freeShipping;

            $shipping = ShippingMethod::query()->find($this->shippingMethodId);
            $isFreeOrPickup = $shipping !== null && ($shipping->type === ShippingMethodType::Free || $shipping->type === ShippingMethodType::Pickup);
            if ($isFreeOrPickup) {
                $shippingCost = 0;
            } elseif ($isCouponFree) {
                $shippingCost = 0;
            } else {
                $dynamicShippingCost = $this->resolveDynamicShippingCost($shippingService, $customer, $subtotalAfterDiscountForShipping);
                $shippingCost = $dynamicShippingCost ?? $shipping?->cost ?? 0;
            }

            $cart->loadMissing('items.variant');
            $hasPreorder = $cart->items->contains(fn ($item) => $item->variant?->fulfillment_strategy?->value === 'preorder');
            if ($hasPreorder && ! $this->preorder_ack) {
                $this->issues = ['Please acknowledge that pre-order items ship around their expected availability date.'];

                return;
            }

            $orderData = [
                'shipping_method_id' => $this->shippingMethodId,
                'payment_method_id' => $this->paymentMethodId,
                'shipping_cost' => $shippingCost,
                'customer_note' => $this->customerNote,
                'preorder_ack_at' => $hasPreorder && $this->preorder_ack ? now() : null,
            ];

            if ($customer) {
                $address = Address::query()->findOrFail($this->selectedAddressId);
                abort_unless($address->customer_id === $customer->id, 403);

                $orderData['shipping_address_id'] = $address->id;
                $orderData['shipping_address'] = array_merge(
                    $address->only([
                        'recipient_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'area', 'postal_code', 'country',
                        'bd_division_id', 'bd_district_id', 'bd_upazila_id',
                    ]),
                    [
                        'bd_division_name' => $address->division?->name_en,
                        'bd_district_name' => $address->district?->name_en,
                        'bd_upazila_name' => $address->upazila?->name_en,
                    ]
                );
            } else {
                $this->validate([
                    'guestName' => ['required', 'string'],
                    'guestEmail' => ['required', 'email'],
                    'guestPhone' => ['required', 'string'],
                    'guestAddress.recipient_name' => ['required', 'string'],
                    'guestAddress.phone' => ['required', 'string'],
                    'guestAddress.address_line_1' => ['required', 'string'],
                    'guestAddress.city' => ['required', 'string'],
                    'bd_division_id' => ['required', 'integer', 'exists:bd_divisions,id'],
                    'bd_district_id' => ['required', 'integer', 'exists:bd_districts,id'],
                    'bd_upazila_id' => ['nullable', 'integer', 'exists:bd_upazilas,id'],
                ]);

                $orderData['guest_name'] = $this->guestName;
                $orderData['guest_email'] = $this->guestEmail;
                $orderData['guest_phone'] = $this->guestPhone;
                $guestSnapshot = array_merge($this->guestAddress, [
                    'bd_division_id' => $this->bd_division_id,
                    'bd_district_id' => $this->bd_district_id,
                    'bd_upazila_id' => $this->bd_upazila_id,
                    'bd_division_name' => $this->divisions->firstWhere('id', $this->bd_division_id)?->name_en,
                    'bd_district_name' => $this->districts->firstWhere('id', $this->bd_district_id)?->name_en,
                    'bd_upazila_name' => $this->upazilas->firstWhere('id', $this->bd_upazila_id)?->name_en,
                ]);
                $orderData['shipping_address'] = $guestSnapshot;
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

    private function resolveDynamicShippingCost(ShippingService $shippingService, mixed $customer, ?int $subtotalAfterDiscount = null): ?int
    {
        // Prefer explicit geo selection (guest) or selected address geo (customer)
        if ($this->bd_upazila_id !== null || $this->bd_district_id !== null) {
            return $shippingService->quote($this->bd_upazila_id, $this->bd_district_id, $this->bd_division_id, null, $subtotalAfterDiscount);
        }

        if ($this->selectedAddressId !== null) {
            $address = Address::query()->find($this->selectedAddressId);
            if ($address !== null) {
                return $shippingService->quote($address->bd_upazila_id, $address->bd_district_id, $address->bd_division_id, $address, $subtotalAfterDiscount);
            }
        }

        if (! empty($this->guestAddress['bd_upazila_id']) || ! empty($this->guestAddress['bd_district_id'])) {
            return $shippingService->quoteForGuest($this->guestAddress, $subtotalAfterDiscount);
        }

        // Fallback to any geo stored in component state even without explicit selection (for initial render)
        if ($subtotalAfterDiscount !== null) {
            // Try with current bd_* even if null — service will fallback to outside rate with threshold check
            $fallback = $shippingService->quote($this->bd_upazila_id, $this->bd_district_id, $this->bd_division_id, null, $subtotalAfterDiscount);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return null;
    }

    private function resolveMatchedRate(ShippingService $shippingService): ?TenantShippingRate
    {
        if ($this->bd_upazila_id !== null || $this->bd_district_id !== null) {
            return $shippingService->getMatchedRate($this->bd_upazila_id, $this->bd_district_id, $this->bd_division_id);
        }

        if ($this->selectedAddressId !== null) {
            $address = Address::query()->find($this->selectedAddressId);
            if ($address !== null) {
                return $shippingService->getMatchedRate($address->bd_upazila_id, $address->bd_district_id, $address->bd_division_id, $address);
            }
        }

        if (! empty($this->guestAddress['bd_upazila_id']) || ! empty($this->guestAddress['bd_district_id'])) {
            return $shippingService->getMatchedRateForGuest($this->guestAddress);
        }

        return $shippingService->getMatchedRate($this->bd_upazila_id, $this->bd_district_id, $this->bd_division_id);
    }

    public function render(CartService $carts, CouponService $coupons, ShippingService $shippingService)
    {
        $customer = Auth::guard('customer')->user();
        $cart = $carts->getOrCreateCart($customer, request()->cookie('cart_token'));
        $cart->load('items.variant.product.translations', 'items.variant.media');
        $subtotal = $cart->items->sum(fn ($item) => $item->lineTotal());
        $couponResult = $coupons->computeForCart($cart, $customer);
        $discount = $couponResult->valid ? $couponResult->discountAmount : 0;
        $subtotalAfterDiscount = max(0, $subtotal - $discount);
        $shipping = ShippingMethod::query()->find($this->shippingMethodId);

        // Unified priority: 1.Method Free/Pickup ->0, 2.Coupon FreeShipping ->0, 3.Geo free_threshold (post-discount) ->0, 4.Geo charge
        $isFreeOrPickup = $shipping !== null && ($shipping->type === ShippingMethodType::Free || $shipping->type === ShippingMethodType::Pickup);
        $isCouponFree = $couponResult->valid && $couponResult->freeShipping;
        $freeReason = null;
        $nextFreeThreshold = null;
        $geoName = null;
        $originalShippingCost = null;
        if ($isFreeOrPickup) {
            $shippingCost = 0;
            $freeReason = $shipping->type === ShippingMethodType::Pickup ? 'Store Pickup' : 'Free Delivery Method';
            $originalShippingCost = $shipping?->cost ?? 0;
        } elseif ($isCouponFree) {
            $shippingCost = 0;
            $freeReason = 'Coupon Applied';
            $matchedRateTmp = $this->resolveMatchedRate($shippingService);
            $originalShippingCost = $matchedRateTmp?->charge ?? $shipping?->cost ?? 0;
        } else {
            $dynamicCost = $this->resolveDynamicShippingCost($shippingService, $customer, $subtotalAfterDiscount);
            $shippingCost = $dynamicCost ?? $shipping?->cost ?? 0;
            // Determine why free or progress toward free
            $matchedRate = $this->resolveMatchedRate($shippingService);
            if ($matchedRate !== null) {
                $originalShippingCost = (int) $matchedRate->charge;
                if ($matchedRate->free_threshold !== null) {
                    $geoName = $matchedRate->district?->name_en ?? $matchedRate->division?->name_en ?? 'your area';
                    if ($shippingCost === 0 && $dynamicCost === 0) {
                        $freeReason = 'Order over '.money((int) $matchedRate->free_threshold);
                    } elseif ($shippingCost !== 0) {
                        $nextFreeThreshold = (int) $matchedRate->free_threshold;
                    }
                }
            } else {
                $originalShippingCost = $shipping?->cost ?? 0;
            }
        }

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

        // Ensure divisions always available for dropdowns
        if (! isset($this->divisions) || $this->divisions->isEmpty()) {
            $this->divisions = BdDivision::query()->orderBy('name_en')->get();
            $this->districts = $this->bd_division_id ? BdDistrict::query()->where('division_id', $this->bd_division_id)->orderBy('name_en')->get() : collect();
            $this->upazilas = $this->bd_district_id ? BdUpazila::query()->where('district_id', $this->bd_district_id)->orderBy('name_en')->get() : collect();
        }

        return view('livewire.checkout-page', [
            'customer' => $customer,
            'addresses' => $customer ? Address::query()->where('customer_id', $customer->id)->get() : collect(),
            'shippingMethods' => ShippingMethod::query()->where('is_active', true)->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->get(),
            'cartItems' => $cart->items,
            'subtotal' => $subtotal,
            'discount' => $couponResult->valid ? $couponResult->discountAmount : 0,
            'shippingCost' => $shippingCost,
            'originalShippingCost' => $originalShippingCost ?? $shippingCost,
            'freeReason' => $freeReason,
            'nextFreeThreshold' => $nextFreeThreshold,
            'geoName' => $geoName,
            'subtotalAfterDiscount' => $subtotalAfterDiscount,
            'hasPreorder' => $hasPreorder,
            'isMixed' => $isMixed,
            'preorderEta' => $preorderEta,
            'divisions' => $this->divisions,
            'districts' => $this->districts,
            'upazilas' => $this->upazilas,
        ]);
    }
}
