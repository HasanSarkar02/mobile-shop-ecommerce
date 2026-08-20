<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('active_reservation_key')->nullable()->after('reservation_expires_at');
            $table->unique(['tenant_id', 'active_reservation_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'active_reservation_key']);
            $table->dropColumn('active_reservation_key');
        });
    }
};
