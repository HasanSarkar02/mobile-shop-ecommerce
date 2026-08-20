<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_events', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')
                ->nullable()
                ->after('note')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('effective_at')->nullable()->after('actor_user_id');
            $table->json('metadata')->nullable()->after('effective_at');
        });

        DB::table('subscription_events')
            ->whereNull('effective_at')
            ->update(['effective_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('subscription_events', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->dropColumn(['actor_user_id', 'effective_at', 'metadata']);
        });
    }
};
