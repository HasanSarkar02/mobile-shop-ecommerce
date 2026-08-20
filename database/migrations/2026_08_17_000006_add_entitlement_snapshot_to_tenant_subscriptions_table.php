<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\TenantSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->string('plan_name')->nullable()->after('plan_id');
            $table->string('billing_period')->nullable()->after('plan_name');
            $table->unsignedBigInteger('price')->nullable()->after('billing_period');
            $table->unsignedInteger('max_products')->nullable()->after('price');
            $table->unsignedInteger('max_staff')->nullable()->after('max_products');
            $table->boolean('custom_domain_allowed')->nullable()->after('max_staff');
        });

        $dangling = TenantSubscription::query()
            ->whereNotNull('plan_id')
            ->whereNotIn('plan_id', Plan::query()->select('id'))
            ->exists();

        if ($dangling) {
            throw new RuntimeException(
                'Cannot snapshot subscription entitlements: some subscriptions reference plans that no longer exist.',
            );
        }

        DB::statement(
            'UPDATE tenant_subscriptions AS ts
             JOIN plans AS p ON p.id = ts.plan_id
             SET ts.plan_name = p.name,
                 ts.billing_period = p.billing_period,
                 ts.price = p.price,
                 ts.max_products = p.max_products,
                 ts.max_staff = p.max_staff,
                 ts.custom_domain_allowed = p.custom_domain_allowed',
        );
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'plan_name',
                'billing_period',
                'price',
                'max_products',
                'max_staff',
                'custom_domain_allowed',
            ]);
        });
    }
};
