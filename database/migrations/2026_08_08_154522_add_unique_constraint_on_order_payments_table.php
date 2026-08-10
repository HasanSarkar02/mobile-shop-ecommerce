<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforces, at the database level, that a given tenant can never have two
     * OrderPayment rows for the same transaction_reference. This is the
     * authoritative guard against SSLCommerz's IPN callback and the browser
     * success redirect both recording the same payment when they race each
     * other; PaymentGatewayService::handleCallback() relies on a unique-key
     * violation here to detect "already processed" instead of a separate
     * check-then-act query.
     *
     * transaction_reference stays nullable (manual/COD payments recorded by
     * an admin may not have one) — MySQL treats each NULL as distinct for a
     * unique index, so multiple no-reference payments are unaffected.
     */
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'transaction_reference'], 'order_payments_tenant_id_transaction_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropUnique('order_payments_tenant_id_transaction_reference_unique');
        });
    }
};