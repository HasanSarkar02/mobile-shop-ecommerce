<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token')->nullable();
            $table->string('name')->default('My Wishlist');
            $table->boolean('is_default')->default(true);
            $table->string('share_token')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'guest_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};