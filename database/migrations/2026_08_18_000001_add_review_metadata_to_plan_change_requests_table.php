<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->string('rejection_reason')->nullable()->after('note');
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_change_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['rejection_reason', 'reviewed_at']);
        });
    }
};
