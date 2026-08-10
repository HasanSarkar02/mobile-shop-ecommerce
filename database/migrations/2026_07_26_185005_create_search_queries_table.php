<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamp('searched_at');

            $table->index(['tenant_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};