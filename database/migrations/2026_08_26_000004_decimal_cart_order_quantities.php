<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase C-3: allow measured goods in cart + orders.
     * Additive only — existing integer rows fit into DECIMAL(10,3) unchanged
     * (1 → 1.000). Reverts safely to unsignedInteger (fractional data would be
     * rounded by MySQL on rollback — documented, acceptable).
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 3)->default('0.000')->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 3)->default('0.000')->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(0)->change();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(0)->change();
        });
    }
};
