<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('base_url')->nullable();
            $table->string('base_url_sandbox')->nullable();
            $table->string('base_url_live')->nullable();
            $table->string('auth_type')->default('api_key');
            $table->json('required_fields')->nullable();
            $table->string('driver_class')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_providers');
    }
};
