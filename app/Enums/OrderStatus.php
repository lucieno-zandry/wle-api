<?php

namespace App\Enums;

use App\Traits\EnumToArray;


enum OrderStatus: string
{
    use EnumToArray;

    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
}
