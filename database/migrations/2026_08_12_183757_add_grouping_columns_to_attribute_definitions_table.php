<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds additive specification-grouping columns to attribute_definitions:
     *   - group:            optional heading for the specification section
     *   - group_sort_order: orders whole groups against each other
     *
     * Both are nullable/defaulted so existing definitions (and the legacy
     * "General/Key Specifications" fallback) keep working untouched.
     */
    public function up(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->string('group')->nullable()->after('unit');
            $table->unsignedInteger('group_sort_order')->default(0)->after('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->dropColumn(['group', 'group_sort_order']);
        });
    }
};
