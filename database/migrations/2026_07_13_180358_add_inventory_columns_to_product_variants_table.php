<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('inventory_type')->default('tracked')->after('sim_type');
            $table->string('fulfillment_strategy')->default('stock')->after('inventory_type');
            $table->string('backorder_policy')->nullable()->after('fulfillment_strategy');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('backorder_policy');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['inventory_type', 'fulfillment_strategy', 'backorder_policy', 'low_stock_threshold']);
        });
    }
};