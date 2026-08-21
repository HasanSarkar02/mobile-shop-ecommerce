<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->string('code')->nullable()->after('tenant_id');
            $table->string('display_name')->nullable()->after('name');
            $table->string('provider')->nullable()->after('type');
            $table->string('account_number')->nullable()->after('provider');
            $table->string('account_name')->nullable()->after('account_number');
            $table->string('bank_name')->nullable()->after('account_name');
            $table->string('branch_name')->nullable()->after('bank_name');
            $table->text('instructions')->nullable()->after('branch_name');
            $table->string('gateway_mode')->default('live')->after('gateway_driver');
            $table->text('credentials')->nullable()->after('gateway_mode');
            $table->string('fee_type')->nullable()->after('credentials');
            $table->integer('fee_value')->nullable()->after('fee_type');
            $table->integer('min_order_amount')->nullable()->after('fee_value');
            $table->integer('max_order_amount')->nullable()->after('min_order_amount');
            $table->boolean('requires_verification')->default(false)->after('max_order_amount');
            $table->string('gateway_ownership')->default('shop')->after('requires_verification');

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropIndex(['tenant_id', 'is_active', 'sort_order']);

            $table->dropColumn([
                'code',
                'display_name',
                'provider',
                'account_number',
                'account_name',
                'bank_name',
                'branch_name',
                'instructions',
                'gateway_mode',
                'credentials',
                'fee_type',
                'fee_value',
                'min_order_amount',
                'max_order_amount',
                'requires_verification',
                'gateway_ownership',
            ]);
        });
    }
};
