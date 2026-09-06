<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subdomain_tombstones', function (Blueprint $table): void {
            $table->id();
            $table->string('subdomain')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('reason'); // trial_0_orders|active_30d|abuse|rejected
            $table->timestamp('released_at')->nullable();
            $table->timestamp('quarantine_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('quarantine_until');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomain_tombstones');
    }
};
