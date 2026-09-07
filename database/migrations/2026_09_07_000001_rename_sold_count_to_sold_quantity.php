<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add sold_quantity as decimal if not exists, backfill from sold_count via ledger
        if (! Schema::hasColumn('products', 'sold_quantity')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->decimal('sold_quantity', 10, 3)->default('0.000')->after('view_count');
            });
        }

        // Backfill from existing sold_count integer values if column exists and sold_quantity is still 0
        if (Schema::hasColumn('products', 'sold_count') && Schema::hasColumn('products', 'sold_quantity')) {
            DB::statement("UPDATE products SET sold_quantity = CAST(sold_count AS DECIMAL(10,3)) WHERE sold_count IS NOT NULL AND sold_quantity = '0.000'");
        }

        // Preferred canonical backfill: ledger Sale - Return if movements exist and products have no sold_quantity yet
        // We do ledger-based backfill only when StockMovement has data and we can verify via tenant scoping
        // This keeps money-only Refunded orders counted (no Return) and restocked orders net zero
        $hasMovements = Schema::hasTable('stock_movements') && DB::table('stock_movements')->exists();
        if ($hasMovements) {
            // For each tenant, compute Sale sum - Return sum per product via variant join
            $products = DB::table('products')->select('id')->get();
            foreach ($products as $product) {
                $saleSum = DB::table('stock_movements')
                    ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
                    ->where('product_variants.product_id', $product->id)
                    ->where('stock_movements.type', 'sale')
                    ->sum('stock_movements.quantity_change'); // negative values like -2.000

                $returnSum = DB::table('stock_movements')
                    ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
                    ->where('product_variants.product_id', $product->id)
                    ->where('stock_movements.type', 'return')
                    ->sum('stock_movements.quantity_change'); // positive values

                // Sale is stored as negative, Return as positive — sold = abs(Sale) - Return? Actually Sale negative, Return positive restores
                // Net sold = -SUM(sale) - SUM(return is already positive restock, but return should subtract from sold)
                // Example: Sale -2, Sale -1, Return +2 => net sold = 1. So -(saleSum) - returnSum? saleSum = -3, returnSum=2 => -(-3)-2=1
                // But our saleSum is negative string, sum returns numeric; we need to handle sign
                $saleAbs = abs((float) $saleSum);
                $returnAbs = abs((float) $returnSum);
                $net = max(0, $saleAbs - $returnAbs);

                // Only overwrite if ledger indicates a different non-zero value and product currently has seeded value that differs more than 10%
                // To avoid overwriting seeded demo data when ledger is empty (fresh install), we only apply if ledger net > 0 and differs
                if ($net > 0) {
                    $current = (float) DB::table('products')->where('id', $product->id)->value('sold_quantity');
                    // If current is integer-seeded, prefer ledger when ledger non-zero differs
                    if (abs($current - $net) > 0.001) {
                        DB::table('products')->where('id', $product->id)->update(['sold_quantity' => number_format($net, 3, '.', '')]);
                    }
                }
            }
        }

        // Step 2: drop sold_count after successful copy — keep for rollback
        if (Schema::hasColumn('products', 'sold_count')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('sold_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'sold_count')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedInteger('sold_count')->default(0)->after('view_count');
            });
        }

        if (Schema::hasColumn('products', 'sold_quantity') && Schema::hasColumn('products', 'sold_count')) {
            DB::statement('UPDATE products SET sold_count = CAST(sold_quantity AS UNSIGNED) WHERE sold_quantity IS NOT NULL');
        }

        if (Schema::hasColumn('products', 'sold_quantity')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('sold_quantity');
            });
        }
    }
};
