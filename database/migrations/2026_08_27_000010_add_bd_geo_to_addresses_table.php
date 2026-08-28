<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->foreignId('bd_division_id')->nullable()->after('tenant_id')->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('bd_district_id')->nullable()->after('bd_division_id')->constrained('bd_districts')->nullOnDelete();
            $table->foreignId('bd_upazila_id')->nullable()->after('bd_district_id')->constrained('bd_upazilas')->nullOnDelete();

            $table->index(['bd_district_id', 'bd_upazila_id']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bd_upazila_id');
            $table->dropConstrainedForeignId('bd_district_id');
            $table->dropConstrainedForeignId('bd_division_id');
        });
    }
};
