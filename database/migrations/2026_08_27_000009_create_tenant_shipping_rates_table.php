<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('bd_division_id')->nullable()->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('bd_district_id')->nullable()->constrained('bd_districts')->nullOnDelete();
            $table->foreignId('bd_upazila_id')->nullable()->constrained('bd_upazilas')->nullOnDelete();
            $table->unsignedBigInteger('charge')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'bd_district_id']);
            $table->index(['tenant_id', 'bd_upazila_id']);
            $table->unique(['tenant_id', 'bd_district_id', 'bd_upazila_id'], 'tenant_geo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_shipping_rates');
    }
};
