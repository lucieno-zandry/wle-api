<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionTypes: string
{
    use EnumToArray;

    case PAYMENT = 'PAYMENT';
    case REFUND = 'REFUND';
    case MANUAL = 'MANUAL';
}
