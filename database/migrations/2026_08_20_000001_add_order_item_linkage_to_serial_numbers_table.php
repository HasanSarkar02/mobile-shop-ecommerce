<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table): void {
            $table->foreignId('order_item_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_item_id');
        });
    }
};
