<?php

declare(strict_types=1);

use Tests\TestCase;

// 1. Tell Pest to use the Laravel TestCase for this file so config() works
uses(TestCase::class);

// 2. Write the Pest test without any class wrapper
it('has after_commit enabled on every queue connection', function (): void {
    $connections = config('queue.connections');

    expect($connections)->not->toBeEmpty();

    foreach ($connections as $name => $connection) {
        if ($name === 'sync') {
            // sync runs inline immediately; after_commit doesn't apply and
            // this driver has no such key.
            continue;
        }

        expect($connection['after_commit'] ?? null)
            ->toBeTrue("Queue connection [{$name}] must have after_commit = true so order-lifecycle jobs dispatched inside DB::transaction() closures (see OrderService, PaymentGatewayService) never run before the transaction commits, regardless of which connection is active.");
    }
});
