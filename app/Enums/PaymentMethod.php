<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Paypal = 'paypal';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';
    case BankTransfer = 'bank_transfer';
    case VanillaPay = 'vanilla_pay';
        // fallback for unknown
    case Unknown = 'unknown';
}
