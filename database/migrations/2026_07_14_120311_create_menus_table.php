<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->default('header');
            $table->timestamps();

            $table->unique(['tenant_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
