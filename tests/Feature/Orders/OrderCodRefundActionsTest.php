<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Filament\Store\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\User;
use App\Services\OrderService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    Queue::fake();
    $tenant = actingAsTenant();
    Auth::guard('web')->login(User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'is_active' => true,
    ]));
    Filament::setCurrentPanel('store');
});

afterEach(function (): void {
    Auth::guard('web')->logout();
});

function codActionMethod(PaymentMethodType $type = PaymentMethodType::Cod): PaymentMethod
{
    return PaymentMethod::query()->create([
        'tenant_id' => tenant()->id,
        'name' => $type->value,
        'type' => $type,
        'is_active' => true,
    ]);
}

/**
 * A shipped order with one consignment, which is the state staff are in when a
 * courier hands cash back.
 *
 * @return array{0: Order, 1: ProductVariant}
 */
function codActionShippedOrder(PaymentMethodType $type = PaymentMethodType::Cod, int $quantity = 2): array
{
    [$cart, $variant] = createCartWithVariant($quantity);

    $orders = app(OrderService::class);
    $order = $orders->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
    ]);

    $order->update(['payment_method_id' => codActionMethod($type)->id]);

    $orders->updateStatus($order->fresh(), OrderStatus::Confirmed);
    $orders->updateStatus($order->fresh(), OrderStatus::Processing);
    $orders->updateStatus($order->fresh(), OrderStatus::Shipped);

    return [$order->fresh(), $variant];
}

describe('Record COD Collection action', function (): void {
    it('is offered on a COD order that still owes money', function (): void {
        [$order] = codActionShippedOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('recordCodCollection');
    });

    it('is hidden on a prepaid order', function (): void {
        // Offering it would invite staff to book cash against an order the courier
        // was never asked to collect for.
        [$order] = codActionShippedOrder(PaymentMethodType::OnlineGateway);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('recordCodCollection');
    });

    it('is hidden once the order is fully paid', function (): void {
        [$order] = codActionShippedOrder();
        app(OrderService::class)->recordPayment(
            $order,
            $order->paymentMethod,
            (int) $order->grand_total,
            OrderPaymentStatus::Paid,
            'prepaid-in-full',
        );

        Livewire::test(ViewOrder::class, ['record' => $order->fresh()->getRouteKey()])
            ->assertActionHidden('recordCodCollection');
    });

    it('books the cash staff confirm, converting taka to minor units', function (): void {
        [$order] = codActionShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('recordCodCollection', [
                'fulfillment_id' => (string) $fulfillment->id,
                'amount' => '250.50',
            ])
            ->assertHasNoActionErrors();

        expect(app(OrderService::class)->amountPaid($order->fresh()))->toBe(25050);
        expect($order->payments()->first()->transaction_reference)->toBe('cod:'.$fulfillment->id);
    });

    it('does not double-book when staff submit the same collection twice', function (): void {
        [$order] = codActionShippedOrder();
        $fulfillment = $order->fulfillments()->firstOrFail();

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);

        $page->callAction('recordCodCollection', [
            'fulfillment_id' => (string) $fulfillment->id,
            'amount' => '100.00',
        ]);

        $page->callAction('recordCodCollection', [
            'fulfillment_id' => (string) $fulfillment->id,
            'amount' => '100.00',
        ])->assertHasNoActionErrors();

        expect($order->payments()->count())->toBe(1);
        expect(app(OrderService::class)->amountPaid($order->fresh()))->toBe(10000);
    });
});

describe('Refund action restock toggle', function (): void {
    it('returns goods to stock when staff tick the toggle', function (): void {
        [$order, $variant] = codActionShippedOrder(PaymentMethodType::Cod, 2);
        $orders = app(OrderService::class);
        $orders->updateStatus($order->fresh(), OrderStatus::Delivered);
        $order = $order->fresh();
        $orders->recordPayment($order, $order->paymentMethod, (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:collected');

        $before = (int) StockItem::query()->where('product_variant_id', $variant->id)->value('quantity');
        $refundable = number_format((int) $order->grand_total / 100, 2, '.', '');

        Livewire::test(ViewOrder::class, ['record' => $order->fresh()->getRouteKey()])
            ->callAction('refundOrder', [
                'amount' => $refundable,
                'reason' => 'Customer returned the goods.',
                'restock' => true,
            ])
            ->assertHasNoActionErrors();

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
        expect((int) StockItem::query()->where('product_variant_id', $variant->id)->value('quantity'))->toBe($before + 2);
    });

    it('leaves stock alone when the toggle is left off', function (): void {
        // The default: the money goes back but the goods have not returned.
        [$order, $variant] = codActionShippedOrder(PaymentMethodType::Cod, 2);
        $orders = app(OrderService::class);
        $orders->updateStatus($order->fresh(), OrderStatus::Delivered);
        $order = $order->fresh();
        $orders->recordPayment($order, $order->paymentMethod, (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:collected');

        $before = (int) StockItem::query()->where('product_variant_id', $variant->id)->value('quantity');
        $refundable = number_format((int) $order->grand_total / 100, 2, '.', '');

        Livewire::test(ViewOrder::class, ['record' => $order->fresh()->getRouteKey()])
            ->callAction('refundOrder', [
                'amount' => $refundable,
                'reason' => 'Refunded to card, goods kept.',
            ])
            ->assertHasNoActionErrors();

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
        expect((int) StockItem::query()->where('product_variant_id', $variant->id)->value('quantity'))->toBe($before);
    });

    it('does not offer the toggle on an order that was never delivered', function (): void {
        // Goods still in transit never came back, so the question must not even be
        // asked — the service would refuse it anyway.
        [$order] = codActionShippedOrder();
        $orders = app(OrderService::class);
        $orders->recordPayment($order, $order->paymentMethod, (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:collected');

        $page = Livewire::test(ViewOrder::class, ['record' => $order->fresh()->getRouteKey()]);
        $page->assertActionVisible('refundOrder');

        $page->mountAction('refundOrder')->assertSchemaComponentHidden('restock');
    });

    it('offers the toggle on a delivered order', function (): void {
        // The counterpart to the test above: without this the hidden assertion would
        // still pass if the toggle were never rendered at all.
        [$order] = codActionShippedOrder();
        $orders = app(OrderService::class);
        $orders->updateStatus($order->fresh(), OrderStatus::Delivered);
        $order = $order->fresh();
        $orders->recordPayment($order, $order->paymentMethod, (int) $order->grand_total, OrderPaymentStatus::Paid, 'cod:collected');

        Livewire::test(ViewOrder::class, ['record' => $order->fresh()->getRouteKey()])
            ->mountAction('refundOrder')
            ->assertSchemaComponentVisible('restock');
    });
});
