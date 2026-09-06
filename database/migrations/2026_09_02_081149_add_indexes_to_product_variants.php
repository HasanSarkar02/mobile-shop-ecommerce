<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // I1: standalone sku index (existing is composite tenant_id+sku)
            if (! $this->indexExists('product_variants', 'product_variants_sku_index')) {
                $table->index('sku');
            }
            // I2: explicit product_id index (FK existed but not guaranteed explicit index)
            if (! $this->indexExists('product_variants', 'product_variants_product_id_index')) {
                $table->index('product_id');
            }
            // I3: is_active index for scopeInStock()
            if (! $this->indexExists('product_variants', 'product_variants_is_active_index')) {
                $table->index('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if ($this->indexExists('product_variants', 'product_variants_sku_index')) {
                $table->dropIndex(['sku']);
            }
            if ($this->indexExists('product_variants', 'product_variants_product_id_index')) {
                $table->dropIndex(['product_id']);
            }
            if ($this->indexExists('product_variants', 'product_variants_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = Schema::getIndexes($table);

            foreach ($indexes as $idx) {
                $name = is_array($idx) ? ($idx['name'] ?? null) : ($idx->name ?? null);
                if ($name === $index) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            // Fallback: assume not exists so migration attempts creation.
        }

        return false;
    }
};
