<?php

namespace App\Rules\Phone;

use App\Support\Phone\PhilippinePhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippinePhone implements ValidationRule
{
    public function __construct(
        private readonly bool $allowMultiple = false,
        private readonly bool $allowShortCodes = false,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $raw = (string) $value;
        $valid = $this->allowMultiple
            ? PhilippinePhoneNumber::isListValid($raw, $this->allowShortCodes)
            : PhilippinePhoneNumber::isValid($raw, $this->allowShortCodes);

        if ($valid) {
            return;
        }

        $fail($this->allowMultiple
            ? 'Each value must be a valid Philippine phone number or hotline, separated by |.'
            : 'Enter a valid Philippine phone number (09XXXXXXXXX or +63…).');
    }
}
