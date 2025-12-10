<?php

namespace HassanDomeDenea\HddLaravelHelpers\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class HexColorRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if the value is a valid 3 or 6 digit hex color code (with or without #)
        if (!preg_match('/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $value)) {
            $fail(__('HddLaravelHelpers::rules.hex_color', ['attribute' => $attribute]));
        }
    }
}
