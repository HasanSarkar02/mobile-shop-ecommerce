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
            $table->timestamp('deletion_requested_at')->nullable()->after('industry');
            $table->timestamp('deletion_scheduled_at')->nullable()->after('deletion_requested_at');
            $table->text('deletion_reason')->nullable()->after('deletion_scheduled_at');
            $table->foreignId('deleted_by_id')->nullable()->after('deletion_reason')->constrained('users')->nullOnDelete();
            $table->string('subdomain_original')->nullable()->after('subdomain');
            $table->softDeletes();
            $table->index('deletion_scheduled_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by_id');
            $table->dropColumn(['deletion_requested_at', 'deletion_scheduled_at', 'deletion_reason', 'subdomain_original', 'deleted_at']);
        });
    }
};
