<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('currency', 3)->default('BDT')->after('logo_path');
            $table->string('primary_color', 7)->default('#16a34a')->after('currency');
            $table->string('contact_email')->nullable()->after('primary_color');
            $table->string('contact_phone')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'currency', 'primary_color', 'contact_email', 'contact_phone']);
        });
    }
};
