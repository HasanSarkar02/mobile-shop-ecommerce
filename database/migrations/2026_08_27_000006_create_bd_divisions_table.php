<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en')->index();
            $table->string('name_bn');
            $table->string('bn_name')->nullable();
            $table->string('bbs_code')->nullable()->unique();
            $table->string('url')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_divisions');
    }
};
