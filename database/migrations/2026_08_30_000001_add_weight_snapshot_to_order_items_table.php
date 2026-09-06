<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('unit_weight_grams')->nullable()->after('unit_cost_price');
            $table->unsignedInteger('line_weight_grams')->nullable()->after('line_cost');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_weight_grams', 'line_weight_grams']);
        });
    }
};
