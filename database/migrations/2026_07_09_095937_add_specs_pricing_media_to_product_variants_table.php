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
            $table->string('color')->nullable()->after('barcode');
            $table->unsignedInteger('storage_gb')->nullable()->after('color');
            $table->unsignedInteger('ram_gb')->nullable()->after('storage_gb');
            $table->string('sim_type')->nullable()->after('ram_gb');
            $table->unsignedBigInteger('compare_at_price')->nullable()->after('price');

            $table->index(['tenant_id', 'color']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['color', 'storage_gb', 'ram_gb', 'sim_type', 'compare_at_price']);
        });
    }
};
