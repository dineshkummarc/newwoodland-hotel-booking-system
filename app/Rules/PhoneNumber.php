<?php

namespace App\Rules;

use App\Services\PhoneNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an international phone number. $region is the country picked
 * next to the input, used only when the number has no "+<code>" prefix.
 */
class PhoneNumber implements ValidationRule
{
    public function __construct(
        protected ?string $region = null,
        protected bool $mustReceiveSms = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phones = app(PhoneNumberService::class);

        if (! is_string($value) || ! $phones->parse($value, $this->region)) {
            $fail('Please enter a valid phone number for the selected country.');

            return;
        }

        if ($this->mustReceiveSms && ! $phones->isSmsCapable($value, $this->region)) {
            $fail('Please enter a mobile number that can receive SMS.');
        }
    }
}
