<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['campaign_id', 'product_id']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('accent_color', 9)->nullable()->after('description');
            $table->string('short_tagline')->nullable()->after('accent_color');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_product');

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['accent_color', 'short_tagline']);
        });
    }
};
