<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_emi_plan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emi_plan_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'emi_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_emi_plan');
    }
};