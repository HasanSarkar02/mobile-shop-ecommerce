<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_upazilas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained('bd_districts')->cascadeOnDelete();
            $table->string('name_en')->index();
            $table->string('name_bn');
            $table->timestamps();

            $table->index('district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_upazilas');
    }
};
