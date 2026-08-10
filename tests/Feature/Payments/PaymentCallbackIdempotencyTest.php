<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\PaymentGatewayService;
use App\Support\PaymentValidationResult;
use App\Support\Tenancy\Tenancy;

/**
 * Stands in for SslcommerzDriver so the test never makes a real HTTP call to
 * the gateway. Always reports the same transaction as successfully valid,
 * which is what SSLCommerz's own validator would consistently return if IPN
 * and the browser redirect both queried it for the same transaction.
 */
class FakeAlwaysValidPaymentDriver
{
    public function __construct(private readonly int $amount)
    {
    }

    public function validateTransaction(string $valId): PaymentValidationResult
    {
        return new PaymentValidationResult('valid', 'tran-id', $this->amount);
    }
}

function bindFakePaymentDriver(int $amount): void
{
    app()->bind(config('payment_gateways.drivers.sslcommerz'), fn () => new FakeAlwaysValidPaymentDriver($amount));
}

it('never creates two OrderPayment rows for two callbacks with the same transaction reference', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $paymentMethod = PaymentMethod::query()->forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'SSLCommerz',
        'type' => \App\Enums\PaymentMethodType::Aggregator,
        'gateway_driver' => 'sslcommerz',
        'is_active' => true,
    ]);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-2026-000001',
        'status' => OrderStatus::Pending,
        'payment_method_id' => $paymentMethod->id,
        'grand_total' => 50000,
    ]);

    bindFakePaymentDriver(50000);

    $tranId = "{$tenant->id}-{$order->order_number}";
    $service = app(PaymentGatewayService::class);

    // Simulates IPN and the browser success redirect both reporting the same
    // transaction — the realistic scenario that used to race on a
    // check-then-act query. handleCallback() is called twice in sequence
    // here (this test process is single-threaded), which exercises the same
    // code path a true race would: the second call must find the unique
    // constraint already satisfied and stop, not create a second row or
    // re-run the status transition.
    $service->handleCallback('val-id', $tranId);
    $service->handleCallback('val-id', $tranId);

    expect(OrderPayment::query()->where('tenant_id', $tenant->id)->where('transaction_reference', $tranId)->count())->toBe(1);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);

    app(Tenancy::class)->set(null);
});

it('enforces the unique (tenant_id, transaction_reference) constraint at the database level', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-2026-000002',
        'status' => OrderStatus::Pending,
        'grand_total' => 10000,
    ]);

    OrderPayment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 10000,
        'status' => 'paid',
        'transaction_reference' => 'dup-ref',
    ]);

    expect(fn () => OrderPayment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 10000,
        'status' => 'paid',
        'transaction_reference' => 'dup-ref',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    app(Tenancy::class)->set(null);
});