<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Support\DatabaseLockRetry;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    actingAsTenant();
});

function p0dOrderData(string $email): array
{
    return ['guest_name' => 'Buyer', 'guest_email' => $email, 'guest_phone' => '01700000000'];
}

function p0dCartForVariants(array $variants): Cart
{
    $cart = Cart::query()->create(['tenant_id' => tenant()->id, 'currency_code' => 'BDT']);

    foreach ($variants as $variant) {
        $cart->items()->create([
            'tenant_id' => tenant()->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => $variant->price,
        ]);
    }

    return $cart;
}

function p0dStockedVariant(): ProductVariant
{
    $variant = createTestVariant();
    app(InventoryService::class)->restock($variant, 10);

    return $variant;
}

it('completes two multi-item carts with opposite variant order without deadlock', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $orderA = app(OrderService::class)->createFromCart(p0dCartForVariants([$v1, $v2]), p0dOrderData('opposite-a@example.com'));
    $orderB = app(OrderService::class)->createFromCart(p0dCartForVariants([$v2, $v1]), p0dOrderData('opposite-b@example.com'));

    expect($orderA->status)->toBe(OrderStatus::Pending);
    expect($orderB->status)->toBe(OrderStatus::Pending);
    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
});

it('locks stock rows in ascending variant order regardless of input order', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $locked = app(InventoryService::class)->lockStockItemsForVariants(collect([$v2, $v1]));

    expect($locked->pluck('product_variant_id')->all())->toBe([$v1->id, $v2->id]);
    expect($locked->pluck('id')->all())->toBe([$v1->stockItems()->first()->id, $v2->stockItems()->first()->id]);
});

it('confirms and restocks a reverse-variant-order order deterministically', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $order = app(OrderService::class)->createFromCart(p0dCartForVariants([$v2, $v1]), p0dOrderData('confirm@example.com'));

    app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);

    expect($v1->stockItems()->first()->fresh()->quantity)->toBe(9);
    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($v2->stockItems()->first()->fresh()->quantity)->toBe(9);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);

    app(OrderService::class)->updateStatus($order, OrderStatus::Cancelled);

    expect($v1->stockItems()->first()->fresh()->quantity)->toBe(10);
    expect($v2->stockItems()->first()->fresh()->quantity)->toBe(10);
});

it('releases reservations of a reverse-variant-order pending order deterministically', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $order = app(OrderService::class)->createFromCart(p0dCartForVariants([$v2, $v1]), p0dOrderData('cancel@example.com'));

    app(OrderService::class)->updateStatus($order, OrderStatus::Cancelled);

    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($v1->stockItems()->first()->fresh()->quantity)->toBe(10);
    expect($v2->stockItems()->first()->fresh()->quantity)->toBe(10);
});

it('changes variants in both directions deterministically', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $order1 = app(OrderService::class)->createFromCart(p0dCartForVariants([$v2]), p0dOrderData('swap-a@example.com'));
    app(OrderService::class)->changeItemVariant($order1, $order1->items->first(), $v1);

    $order2 = app(OrderService::class)->createFromCart(p0dCartForVariants([$v1]), p0dOrderData('swap-b@example.com'));
    app(OrderService::class)->changeItemVariant($order2, $order2->items->first(), $v2);

    expect($order1->items->first()->fresh()->product_variant_id)->toBe($v1->id);
    expect($order2->items->first()->fresh()->product_variant_id)->toBe($v2->id);
    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('keeps exact serial-to-order-item attribution on confirm and cancellation return', function () {
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(3)->for($variant, 'variant')->create(['status' => 'available']);

    $cart = p0dCartForVariants([$variant]);
    $cart->items()->first()->update(['quantity' => 2]);

    $order = app(OrderService::class)->createFromCart($cart, p0dOrderData('serial@example.com'));

    app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);

    $item = $order->items()->first();
    $sold = SerialNumber::query()
        ->where('product_variant_id', $variant->id)
        ->where('status', 'sold')
        ->get();

    expect($sold)->toHaveCount(2);
    expect($sold->pluck('order_item_id')->unique()->all())->toBe([$item->id]);

    app(OrderService::class)->updateStatus($order, OrderStatus::Cancelled);

    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'available')->count())->toBe(3);
    expect(SerialNumber::query()->where('product_variant_id', $variant->id)->whereNotNull('order_item_id')->count())->toBe(0);
});

it('reserves the same variant across carts without overselling', function () {
    $variant = p0dStockedVariant();
    $variant->stockItems()->first()->update(['quantity' => 2, 'reserved_quantity' => 0]);

    app(OrderService::class)->createFromCart(p0dCartForVariants([$variant]), p0dOrderData('stock-a@example.com'));
    app(OrderService::class)->createFromCart(p0dCartForVariants([$variant]), p0dOrderData('stock-b@example.com'));

    expect(fn () => app(OrderService::class)->createFromCart(p0dCartForVariants([$variant]), p0dOrderData('stock-c@example.com')))
        ->toThrow(InvalidOrderStateException::class);

    expect($variant->stockItems()->first()->fresh()->reserved_quantity)->toBe(2);
});

it('retries a synthetic deadlock and succeeds on the next attempt', function () {
    $attempts = 0;
    $deadlock = new QueryException(DB::connection()->getName(), 'select 1', [], new PDOException('Deadlock found when trying to get lock', 1213));

    $result = DatabaseLockRetry::run(function () use (&$attempts, $deadlock) {
        $attempts++;

        if ($attempts === 1) {
            throw $deadlock;
        }

        return 'ok';
    });

    expect($result)->toBe('ok');
    expect($attempts)->toBe(2);
});

it('retries a real lock-wait timeout and succeeds once the lock is released', function () {
    $config = config('database.connections.mysql');
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password']
    );

    // A throwaway table avoids the RefreshDatabase transaction pinning locks on
    // rows the main connection created in this test.
    $pdo->exec('DROP TABLE IF EXISTS _lock_retry_test');
    $pdo->exec('CREATE TABLE _lock_retry_test (id INT PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO _lock_retry_test (id, value) VALUES (1, 0), (2, 0)');

    $pdo->beginTransaction();
    $pdo->exec('UPDATE _lock_retry_test SET value = value WHERE id = 2');

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        $attempts = 0;

        $result = DatabaseLockRetry::run(function () use (&$attempts, $pdo) {
            $attempts++;

            if ($attempts === 2) {
                $pdo->exec('COMMIT');
            }

            DB::table('_lock_retry_test')->where('id', 1)->lockForUpdate()->first();

            if ($attempts === 1) {
                DB::table('_lock_retry_test')->where('id', 2)->lockForUpdate()->first();
            }

            return 'ok';
        });

        expect($result)->toBe('ok');
        expect($attempts)->toBe(2);
    } finally {
        $pdo->exec('COMMIT');
        DB::statement('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        $pdo->exec('DROP TABLE IF EXISTS _lock_retry_test');
    }
});

it('stops retrying after the attempt limit and rethrows the deadlock', function () {
    $attempts = 0;
    $deadlock = new QueryException(DB::connection()->getName(), 'select 1', [], new PDOException('Deadlock found when trying to get lock', 1213));

    try {
        DatabaseLockRetry::run(function () use (&$attempts, $deadlock) {
            $attempts++;

            throw $deadlock;
        }, 2);

        $this->fail('Expected the deadlock to be rethrown after retry exhaustion.');
    } catch (QueryException $e) {
        expect($attempts)->toBe(2);
    }
});

it('does not retry business-rule or plain SQL exceptions', function () {
    $businessAttempts = 0;

    try {
        DatabaseLockRetry::run(function () use (&$businessAttempts) {
            $businessAttempts++;

            throw new RuntimeException('Invalid business rule');
        });

        $this->fail('Expected the business-rule exception to propagate.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Invalid business rule');
        expect($businessAttempts)->toBe(1);
    }

    $sqlAttempts = 0;
    $sqlError = new QueryException(DB::connection()->getName(), 'insert into orders', [], new PDOException('Duplicate entry', 1062));

    try {
        DatabaseLockRetry::run(function () use (&$sqlAttempts, $sqlError) {
            $sqlAttempts++;

            throw $sqlError;
        });

        $this->fail('Expected the plain SQL exception to propagate.');
    } catch (QueryException $e) {
        expect($sqlAttempts)->toBe(1);
    }
});

it('keeps tenant isolation while locking stock deterministically', function () {
    $v1 = p0dStockedVariant();

    $order = app(OrderService::class)->createFromCart(p0dCartForVariants([$v1]), p0dOrderData('iso@example.com'));

    expect($order->status)->toBe(OrderStatus::Pending);
    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);

    $otherTenant = Tenant::factory()->create();
    app(Tenancy::class)->set($otherTenant);

    $v2 = p0dStockedVariant();
    $otherOrder = app(OrderService::class)->createFromCart(p0dCartForVariants([$v2]), p0dOrderData('iso@example.com'));

    expect($otherOrder->status)->toBe(OrderStatus::Pending);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(1);
});

it('keeps the P0-A stale-price rejection intact for multi-variant checkouts', function () {
    $v1 = p0dStockedVariant();
    $v2 = p0dStockedVariant();

    $cart = p0dCartForVariants([$v1, $v2]);
    $stalePrice = $v2->price;
    $v2->update(['price' => $stalePrice + 100]);

    expect(fn () => app(OrderService::class)->createFromCart($cart, p0dOrderData('price@example.com')))
        ->toThrow(InvalidOrderStateException::class, 'has changed');

    expect(Order::query()->count())->toBe(0);
    expect($cart->fresh()->converted_at)->toBeNull();
    expect($v1->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
    expect($v2->stockItems()->first()->fresh()->reserved_quantity)->toBe(0);
});
