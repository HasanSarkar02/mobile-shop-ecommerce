<?php

declare(strict_types=1);

use App\Services\SubscriptionChargeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            $table->foreignId('subscription_charge_id')
                ->nullable()
                ->after('intent')
                ->constrained('subscription_charges')
                ->nullOnDelete();
        });

        app(SubscriptionChargeService::class)->backfillVerifiedPayments();
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_charge_id');
        });
    }
};
