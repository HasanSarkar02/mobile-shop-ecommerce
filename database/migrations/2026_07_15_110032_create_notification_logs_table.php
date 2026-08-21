<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key');
            $table->string('channel');
            $table->nullableMorphs('recipient');
            $table->string('recipient_address');
            $table->nullableMorphs('related');
            $table->string('subject_rendered')->nullable();
            $table->text('body_rendered');
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('idempotency_key');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
