<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('delivery_zone_snapshot', 255)->nullable()->after('shipping_cost');
            $table->unsignedInteger('total_weight_grams')->nullable()->after('cost_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['delivery_zone_snapshot', 'total_weight_grams']);
        });
    }
};
