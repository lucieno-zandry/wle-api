<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CancellableOrder implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $order, Closure $fail): void
    {
        // Executes Policy checks and retrieves the exact deny message if it fails
        if (!$order->isCancellable()) {
            $fail('This order is already being processed or cancelled.');
        }
    }
}
