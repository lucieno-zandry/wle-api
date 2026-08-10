<?php

namespace App\Rules;

use App\Enums\OrderStatus;
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
        // Validate Allowed Window: Cannot cancel if processing or beyond
        $disallowedStatuses = [
            OrderStatus::PROCESSING,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED
        ];

        if (in_array($order->status, $disallowedStatuses)) {
            $fail('This order is already being processed and cannot be cancelled.');
        }

        if ($order->status === OrderStatus::CANCELLED) {
            $fail('Order is already cancelled.');
        }
    }
}
