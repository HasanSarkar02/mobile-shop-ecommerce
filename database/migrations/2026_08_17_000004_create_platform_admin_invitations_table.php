<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_digest', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('resend_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'consumed_at', 'expires_at'], 'platform_admin_invitations_lifecycle_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_invitations');
    }
};
