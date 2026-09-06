<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('sold_count')->default(0)->after('view_count');
            $table->unsignedInteger('rating_1_count')->default(0)->after('reviews_count');
            $table->unsignedInteger('rating_2_count')->default(0)->after('rating_1_count');
            $table->unsignedInteger('rating_3_count')->default(0)->after('rating_2_count');
            $table->unsignedInteger('rating_4_count')->default(0)->after('rating_3_count');
            $table->unsignedInteger('rating_5_count')->default(0)->after('rating_4_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['sold_count', 'rating_1_count', 'rating_2_count', 'rating_3_count', 'rating_4_count', 'rating_5_count']);
        });
    }
};
