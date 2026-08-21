<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;

class SequenceGenerator
{
    public function next(int $tenantId, string $key): int
    {
        return DB::transaction(function () use ($tenantId, $key): int {
            $counter = SequenceCounter::query()
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = SequenceCounter::query()->create(['tenant_id' => $tenantId, 'key' => $key, 'value' => 0]);
                $counter = SequenceCounter::query()->where('id', $counter->id)->lockForUpdate()->first();
            }

            $counter->increment('value');

            return $counter->value;
        }, 3);
    }

    public function nextFormatted(int $tenantId, string $key, string $prefix): string
    {
        $value = $this->next($tenantId, $key);

        return sprintf('%s-%s-%06d', $prefix, now()->format('Y'), $value);
    }
}
