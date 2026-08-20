<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('primary_owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('owner_onboarding')->after('source');
            $table->foreignId('previous_primary_owner_id')->nullable()->after('invited_by')->constrained('users')->nullOnDelete();
        });

        DB::table('tenants')->orderBy('id')->each(function (object $tenant): void {
            $ownerId = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('role', 'owner')
                ->orderBy('id')
                ->value('id');

            if ($ownerId !== null) {
                DB::table('tenants')->where('id', $tenant->id)->update(['primary_owner_id' => $ownerId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->dropForeign(['previous_primary_owner_id']);
            $table->dropColumn(['purpose', 'previous_primary_owner_id']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign(['primary_owner_id']);
            $table->dropColumn('primary_owner_id');
        });
    }
};
