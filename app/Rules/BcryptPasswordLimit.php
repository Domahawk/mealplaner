<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BcryptPasswordLimit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Bcrypt truncates after 72 bytes and cannot hash null bytes.
        if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
            $fail('The password must be at most 72 bytes and contain no null bytes.');
        }
    }
}
