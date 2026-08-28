<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backend-1.1: base quantity that variant_base_price covers.
     * Enables proportional pricing: (price / base_qty) * qty.
     * Null = 1.000 (price per 1 unit) — preserves existing behaviour.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('base_uom_quantity', 10, 3)
                ->nullable()
                ->after('sell_by_unit');
        });

        // Backfill existing rows: base = sell_by_unit ?? 1.000
        DB::table('products')->whereNull('base_uom_quantity')->update([
            'base_uom_quantity' => DB::raw('COALESCE(sell_by_unit, 1.000)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('base_uom_quantity');
        });
    }
};
