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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->json('locales')->nullable()->after('currency');
            $table->string('preferred_locale', 5)->default('en')->after('locales');
        });

        // Backfill existing tenants (created before this migration) to the safe
        // default. New rows get the DB default via the model cast, but existing
        // rows would otherwise stay NULL and fail enabledLocales().
        DB::table('tenants')->whereNull('locales')->update(['locales' => json_encode(['en'])]);

        // Ensure any empty string preferred_locale is normalized.
        DB::table('tenants')->where('preferred_locale', '')->update(['preferred_locale' => 'en']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['locales', 'preferred_locale']);
        });
    }
};
