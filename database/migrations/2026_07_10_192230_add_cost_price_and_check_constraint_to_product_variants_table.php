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
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_price')->nullable()->after('compare_at_price');
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT chk_variant_discount CHECK (compare_at_price IS NULL OR compare_at_price > price)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_variants DROP CHECK chk_variant_discount');

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('cost_price');
        });
    }
};
