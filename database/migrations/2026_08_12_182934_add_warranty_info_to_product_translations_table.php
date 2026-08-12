<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the storefront warranty terms column for product translations.
     * The PDP view (Warranty section), the Filament ProductResource form, and
     * the ProductListingService warranty filter already reference
     * product_translations.warranty_info; this migration finally makes that
     * column exist so those references work instead of failing/silently
     * dropping data.
     */
    public function up(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->longText('warranty_info')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropColumn('warranty_info');
        });
    }
};
