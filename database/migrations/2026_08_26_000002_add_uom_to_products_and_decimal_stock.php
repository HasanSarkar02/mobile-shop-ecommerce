<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase C-1 (PLAN #46/#47): per-product sell unit + measured-goods stock.
     *
     * Additive only. stock_items.quantity AND reserved_quantity move from
     * unsignedInteger to DECIMAL(10,3) — reserved must match quantity or a
     * 1.500 kg reservation cannot be represented; existing integer rows fit
     * into the new scale unchanged (5 → 5.000). stock_movements mirrors both
     * columns so the audit ledger records fractional changes exactly.
     * down() reverts to the original integer types (fractional data would be
     * rounded by MySQL on revert — documented, acceptable rollback path).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('uom_id')
                ->nullable()
                ->after('base_price')
                ->constrained('unit_of_measures')
                ->nullOnDelete();
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 3)->default(0)->change();
            $table->decimal('reserved_quantity', 10, 3)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('quantity_change', 10, 3)->change();
            $table->decimal('quantity_after', 10, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->integer('quantity_change')->change();
            $table->unsignedInteger('quantity_after')->default(0)->change();
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(0)->change();
            $table->unsignedInteger('reserved_quantity')->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uom_id');
        });
    }
};
