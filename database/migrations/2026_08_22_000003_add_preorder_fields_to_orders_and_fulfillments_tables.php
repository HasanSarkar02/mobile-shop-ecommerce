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
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('preorder_ack_at')->nullable()->after('internal_note');
        });

        Schema::table('order_fulfillments', function (Blueprint $table): void {
            $table->string('fulfillment_group')->default('stock')->after('status');
            $table->timestamp('expected_available_at')->nullable()->after('fulfillment_group');
            $table->index(['order_id', 'fulfillment_group']);
        });

        // Backfill existing rows as stock group for backward compatibility.
        DB::table('order_fulfillments')->whereNull('fulfillment_group')->orWhere('fulfillment_group', '')->update(['fulfillment_group' => 'stock']);
        DB::table('order_fulfillments')->where('fulfillment_group', '')->update(['fulfillment_group' => 'stock']);
    }

    public function down(): void
    {
        Schema::table('order_fulfillments', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'fulfillment_group']);
            $table->dropColumn(['fulfillment_group', 'expected_available_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('preorder_ack_at');
        });
    }
};
