<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('fulfillment_strategy')->nullable()->after('line_total');
            $table->timestamp('expected_available_at')->nullable()->after('fulfillment_strategy');
            $table->foreignId('order_fulfillment_id')->nullable()->after('expected_available_at')->constrained('order_fulfillments')->nullOnDelete();
            $table->index(['order_id', 'fulfillment_strategy']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'fulfillment_strategy']);
            $table->dropForeign(['order_fulfillment_id']);
            $table->dropColumn(['fulfillment_strategy', 'expected_available_at', 'order_fulfillment_id']);
        });
    }
};
