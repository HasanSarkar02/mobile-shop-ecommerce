<?php

declare(strict_types=1);

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
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $productId,
        private readonly int $tenantId,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        // Product carries the BelongsToTenant global scope, which throws when no
        // tenant context is resolved. A queue worker has none, so we set it
        // explicitly for the lifetime of this job and always restore afterwards.
        $tenancy = app(Tenancy::class);
        $previous = $tenancy->get();

        try {
            $tenancy->set($tenant);
            Product::query()->where('id', $this->productId)->increment('view_count');
        } finally {
            $tenancy->set($previous);
        }
    }
}
