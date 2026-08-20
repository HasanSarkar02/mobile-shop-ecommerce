<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->default('platform');
            $table->string('token_digest', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('resend_count')->default(0);
            $table->string('delivery_status', 32)->default('queued');
            $table->text('delivery_error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'revoked_at', 'consumed_at', 'expires_at'], 'tenant_invitations_lifecycle_index');
            $table->index(['delivery_status', 'expires_at'], 'tenant_invitations_delivery_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
