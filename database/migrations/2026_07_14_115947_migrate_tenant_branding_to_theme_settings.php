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
        DB::table('tenants')->select('id', 'logo_path', 'primary_color')->orderBy('id')->each(function ($tenant): void {
            DB::table('store_theme_settings')->updateOrInsert(
                ['tenant_id' => $tenant->id],
                [
                    'logo_path' => $tenant->logo_path,
                    'primary_color' => $tenant->primary_color ?: '#16a34a',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('store_settings')->updateOrInsert(
                ['tenant_id' => $tenant->id],
                ['created_at' => now(), 'updated_at' => now()],
            );
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'primary_color']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('primary_color', 7)->default('#16a34a')->after('currency');
        });
    }
};
