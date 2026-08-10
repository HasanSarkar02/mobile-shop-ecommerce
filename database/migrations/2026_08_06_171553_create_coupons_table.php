<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedInteger('value')->nullable();
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->string('eligibility_scope')->default('all');
            $table->string('scope_mode')->default('include');
            $table->string('customer_eligibility')->default('all');
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        DB::statement("ALTER TABLE coupons ADD CONSTRAINT chk_coupon_percentage_range CHECK (type != 'percentage' OR (value BETWEEN 0 AND 100))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE coupons DROP CHECK chk_coupon_percentage_range');
        Schema::dropIfExists('coupons');
    }
};