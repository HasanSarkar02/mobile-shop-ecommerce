<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSubdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtolower((string) $value);

        if (! preg_match('/^[a-z0-9]([a-z0-9-]{1,28}[a-z0-9])?$/', $value)) {
            $fail('Subdomain must be 3-30 characters: lowercase letters, numbers, and hyphens only.');

            return;
        }

        if (in_array($value, config('tenancy.reserved_subdomains', []), true)) {
            $fail('This subdomain is reserved and cannot be used.');

            return;
        }

        if (Tenant::query()->where('subdomain', $value)->exists()) {
            $fail('This subdomain is already taken.');
        }
    }
}