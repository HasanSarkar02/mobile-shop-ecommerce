<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_platform_admin');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('deactivated_at');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'deactivated_at', 'password_changed_at', 'last_login_at']);
        });
    }
};
