<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_theme_settings', function (Blueprint $table): void {
            $table->boolean('whatsapp_widget_enabled')->default(false)->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('store_theme_settings', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_widget_enabled');
        });
    }
};
