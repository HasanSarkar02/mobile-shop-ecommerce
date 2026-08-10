<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IncrementProductViewCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $productId, 
        public int $tenantId // Received from the test/controller
    ) {}

    public function handle(): void
    {
        // 1. Manually restore the tenant context FIRST
        $tenant = Tenant::withoutGlobalScope('tenant')->find($this->tenantId);
        
        if ($tenant) {
            app(Tenancy::class)->set($tenant);
        }

        // 2. Now you can safely query tenant-scoped models!
        $product = Product::find($this->productId);
        
        if ($product) {
            $product->increment('view_count');
        }
    }
}