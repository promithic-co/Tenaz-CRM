<?php

namespace App\Rules;

use App\Exceptions\SmartList\InvalidFiltersException;
use App\Services\SmartList\FilterSchema;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a JSON string (or pre-decoded array) conforms to the
 * smart list FilterSchema.
 *
 * Usage in a Form Request:
 *   'filters_json' => ['required', new ValidFilterJson],
 */
class ValidFilterJson implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            // Already decoded (e.g. submitted as JSON body with Content-Type: application/json)
            try {
                FilterSchema::validate($value);
            } catch (InvalidFiltersException $e) {
                $fail($e->getMessage());
            }

            return;
        }

        if (! is_string($value)) {
            $fail("O campo $attribute deve ser um objeto JSON ou string JSON válida.");

            return;
        }

        try {
            FilterSchema::validateRaw($value);
        } catch (InvalidFiltersException $e) {
            $fail($e->getMessage());
        }
    }
}
