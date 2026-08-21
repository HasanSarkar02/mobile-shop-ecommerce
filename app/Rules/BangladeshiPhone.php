<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BangladeshiPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! preg_match('/^01[3-9]\d{8}$/', (string) $value)) {
            $fail('Enter a valid Bangladeshi mobile number, e.g. 01712345678.');
        }
    }
}
