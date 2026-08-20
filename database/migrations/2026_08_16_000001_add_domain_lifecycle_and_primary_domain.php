<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->string('normalized_domain')->nullable()->unique()->after('domain');
            $table->string('status')->default('pending')->index()->after('verified_at');
            $table->string('verification_method')->nullable()->after('status');
            $table->string('verification_token_digest', 64)->nullable()->after('verification_method');
            $table->string('verification_record_name')->nullable()->after('verification_token_digest');
            $table->timestamp('verification_started_at')->nullable()->after('verification_record_name');
            $table->timestamp('verification_expires_at')->nullable()->after('verification_started_at');
            $table->unsignedInteger('verification_attempts')->default(0)->after('verification_expires_at');
            $table->timestamp('last_checked_at')->nullable()->after('verification_attempts');
            $table->string('verification_failure_code')->nullable()->after('last_checked_at');
            $table->text('verification_failure_message')->nullable()->after('verification_failure_code');
            $table->timestamp('activated_at')->nullable()->after('verification_failure_message');
            $table->timestamp('revoked_at')->nullable()->after('activated_at');
            $table->text('revocation_reason')->nullable()->after('revoked_at');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('primary_domain_id')
                ->nullable()
                ->after('id')
                ->constrained('domains')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_domain_id');
        });

        Schema::table('domains', function (Blueprint $table): void {
            $table->dropUnique(['normalized_domain']);
            $table->dropColumn([
                'normalized_domain',
                'status',
                'verification_method',
                'verification_token_digest',
                'verification_record_name',
                'verification_started_at',
                'verification_expires_at',
                'verification_attempts',
                'last_checked_at',
                'verification_failure_code',
                'verification_failure_message',
                'activated_at',
                'revoked_at',
                'revocation_reason',
            ]);
        });
    }
};
